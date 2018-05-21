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
        return {
          config: response.data.config,
          settingsStrings: response.data.settingsStrings
        }
      })
  }
}
