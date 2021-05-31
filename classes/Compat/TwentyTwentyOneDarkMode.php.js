(function () {
  var isDarkMode = window.matchMedia("(prefers-color-scheme: dark)").matches;

  if ("yes" === window.localStorage.getItem("twentytwentyoneDarkMode")) {
    isDarkMode = true;
  } else if ("no" === window.localStorage.getItem("twentytwentyoneDarkMode")) {
    isDarkMode = false;
  }

  if (isDarkMode) {
    document.documentElement.classList.add("is-dark-theme");
    document.body.classList.add("is-dark-theme");
  } else {
    document.documentElement.classList.remove("is-dark-theme");
    document.body.classList.remove("is-dark-theme");
  }
})();
