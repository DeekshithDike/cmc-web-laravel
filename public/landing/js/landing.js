(() => {
  const header = document.getElementById("siteHeader");
  const toggle = document.getElementById("navToggle");
  const drawer = document.getElementById("navDrawer");
  const scrollTop = document.getElementById("scrollTop");

  const onScroll = () => {
    const y = window.scrollY || 0;
    if (header) header.classList.toggle("is-solid", y > 40);
    if (scrollTop) scrollTop.classList.toggle("show", y > 420);
  };

  window.addEventListener("scroll", onScroll, { passive: true });
  onScroll();

  if (toggle && drawer) {
    toggle.addEventListener("click", () => {
      const open = drawer.classList.toggle("open");
      toggle.setAttribute("aria-expanded", open ? "true" : "false");
    });

    drawer.querySelectorAll("a").forEach((link) => {
      link.addEventListener("click", () => {
        drawer.classList.remove("open");
        toggle.setAttribute("aria-expanded", "false");
      });
    });
  }

  if (scrollTop) {
    scrollTop.addEventListener("click", () => {
      window.scrollTo({ top: 0, behavior: "smooth" });
    });
  }
})();
