var imageRequest = new XMLHttpRequest();
imageRequest.open('GET', imageUrl);
imageRequest.onload = function () {
  var success = imageRequest.status >= 200 && imageRequest.status < 300;
  configureRequestsFormat(success)
};
imageRequest.onerror = function () {
    configureRequestsFormat(false);
};
imageRequest.ontimeout = function () {
    configureRequestsFormat(false);
};
imageRequest.send();

function configureRequestsFormat(withPathInfo) {
    var data = new FormData();
    data.append('action', 'phastpress_save_config');
    data.append('phastpress-pathinfo-query-format', withPathInfo ? 'on' : 'off');
    data.append('_wpnonce', nonce);
    var configureRequest = new XMLHttpRequest();
    configureRequest.open('POST', ajaxurl);
    configureRequest.send(data);
}
