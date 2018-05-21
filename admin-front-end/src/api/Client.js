import axios from 'axios'

export default class WordPressAPIClient {
  constructor (adminUrl) {
    this._adminUrl = adminUrl
    this._nonceName = null
    this._nonce = null
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
    const data = new FormData()
    data.set('action', 'phastpress_save_config')
    data.set(this._nonceName, this._nonce)
    Object.keys(config).forEach(key => {
      data.set('phastpress-' + key, config[key] ? 'on' : 'off')
    })
    return axios.post(this._adminUrl, data)
      .then(response => response.data)
  }
}
