import axios from 'axios'

class RequestsAggregator {
  constructor (makeRequestCb) {
    this._makeRequestCb = makeRequestCb
    this._lastRequestParams = null
    this._resolvers = []
    this._active = false
  }

  execute (requestParams) {
    this._lastRequestParams = requestParams
    const p = new Promise((resolve, reject) => this._resolvers.push({resolve, reject}))
    if (!this._active) {
      this._active = true
      this._doRequest()
    }
    return p
  }

  async _doRequest () {
    const params = this._lastRequestParams
    const resolvers = this._resolvers
    this._lastRequestParams = null
    this._resolvers = []
    try {
      const response = await this._makeRequestCb(params)
      resolvers.forEach(({resolve}) => resolve(response.data))
    } catch (e) {
      resolvers.forEach(({reject}) => reject(e))
    }
    if (this._lastRequestParams) {
      setTimeout(this._doRequest.bind(this))
    } else {
      this._active = false
    }
  }
}

export default class WordPressAPIClient {
  constructor (adminUrl) {
    this._adminUrl = adminUrl
    this._nonceName = null
    this._nonce = null
    this._saveAggregator = new RequestsAggregator(this._doConfigSave.bind(this))

    this.onsavestarted = () => {}
    this.onsavefinished = () => {}
  }

  getAdminPanelData () {
    return axios.get(this._adminUrl + '?action=phastpress_get_admin_panel_data')
      .then(response => {
        this._nonceName = response.data.nonceName
        this._nonce = response.data.nonce
        delete response.data.nonce
        delete response.data.nonceName
        return response.data
      })
  }

  saveConfig (config) {
    return this._saveAggregator.execute(config)
  }

  _doConfigSave (config) {
    const data = new FormData()
    data.set('action', 'phastpress_save_config')
    data.set(this._nonceName, this._nonce)
    Object.keys(config).forEach(key => {
      data.set('phastpress-' + key, config[key] ? 'on' : 'off')
    })
    this.onsavestarted()
    return axios.post(this._adminUrl, data)
      .finally(() => {
        this.onsavefinished()
      })
  }
}
