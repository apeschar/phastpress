if (
  window.matchMedia("(prefers-color-scheme: dark)").matches ||
  window.localStorage.getItem("twentytwentyoneDarkMode") === "yes"
) {
  console.log("[PhastPress] Add is-dark-theme class to <HTML> and <BODY>");
  document.documentElement.classList.add("is-dark-theme");
  document.body.classList.add("is-dark-theme");
}
