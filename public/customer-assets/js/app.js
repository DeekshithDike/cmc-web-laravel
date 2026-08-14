(function () {
  const themeKey = "cmc-theme";
  const sidebarKey = "cmc-sidebar";
  const saved = localStorage.getItem(themeKey);
  if (saved !== "light") document.documentElement.classList.add("dark");
  if (localStorage.getItem(sidebarKey) !== "expanded") document.documentElement.classList.add("sidebar-collapsed");

  window.cmcToggleTheme = function () {
    document.documentElement.classList.toggle("dark");
    localStorage.setItem(themeKey, document.documentElement.classList.contains("dark") ? "dark" : "light");
  };

  function closeMobile() {
    const s = document.getElementById("mobile-sidebar");
    const o = document.getElementById("mobile-sidebar-overlay");
    if (!s || !o) return;
    s.classList.add("-translate-x-full");
    o.classList.add("opacity-0", "hidden");
  }
  function openMobile() {
    const s = document.getElementById("mobile-sidebar");
    const o = document.getElementById("mobile-sidebar-overlay");
    if (!s || !o) return;
    o.classList.remove("hidden");
    requestAnimationFrame(() => o.classList.remove("opacity-0"));
    s.classList.remove("-translate-x-full");
  }
  window.closeMobileSidebar = closeMobile;
  window.openMobileSidebar = openMobile;

  document.addEventListener("click", (e) => {
    const btn = e.target.closest(".sidebar-toggle-btn");
    if (!btn) return;
    if (window.matchMedia("(min-width: 1024px)").matches) {
      document.documentElement.classList.toggle("sidebar-collapsed");
      localStorage.setItem(sidebarKey, document.documentElement.classList.contains("sidebar-collapsed") ? "collapsed" : "expanded");
    } else {
      const s = document.getElementById("mobile-sidebar");
      if (s && s.classList.contains("-translate-x-full")) openMobile(); else closeMobile();
    }
  });

  document.querySelectorAll("[data-password-toggle]").forEach((btn) => {
    const input = btn.closest(".relative")?.querySelector("input");
    const icon = btn.querySelector("i");
    if (!input) return;
    btn.addEventListener("click", () => {
      const show = input.type === "password";
      input.type = show ? "text" : "password";
      if (icon) { icon.classList.toggle("ph-eye", !show); icon.classList.toggle("ph-eye-slash", show); }
    });
  });
})();
