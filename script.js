document.addEventListener("DOMContentLoaded", load);

function load() {
  navActiveEffect();
}

// navigation active effect
function navActiveEffect() {
  var url = window.location.pathname;
  var menuItems = document.querySelectorAll(".navigation-link");
  menuItems.forEach(function (item) {
    if (url.includes(item.getAttribute("href"))) {
      item.classList.add("navigation-active");
    }
  });
}
