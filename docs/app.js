const locale = document.documentElement.lang.toLowerCase().startsWith("pt") ? "pt" : "en";
const i18n = {
  en: {
    copy: "copy",
    copied: "copied",
    rootHit: "root() match → json({ message: StemPHP API })",
    rootMiss: "root() miss",
    onUsers: (rest) => `on('users') consume → remaining /${rest}`,
    getList: "get() terminal → json([{ id: 1, name: John }])",
    postCreate: "post() terminal → json({ id: 2 }, 201)",
    onInt: (id, rest) => `onInt() capture ${id} → remaining /${rest}`,
    getShow: (id) => `get() terminal → json({ id: ${id}, name: John })`,
    del: "delete() → halt(204)",
    emptyBranch: "branch taken, no leaf → 200 empty",
    onIntMiss: "onInt() miss — branch already taken → 200 empty",
    onUsersMiss: "on('users') miss",
    notFound: "App::handle() → 404 Not Found",
  },
  pt: {
    copy: "copiar",
    copied: "copiado",
    rootHit: "root() casa → json({ message: StemPHP API })",
    rootMiss: "root() não casa",
    onUsers: (rest) => `on('users') consome → restante /${rest}`,
    getList: "get() folha → json([{ id: 1, name: John }])",
    postCreate: "post() folha → json({ id: 2 }, 201)",
    onInt: (id, rest) => `onInt() captura ${id} → restante /${rest}`,
    getShow: (id) => `get() folha → json({ id: ${id}, name: John })`,
    del: "delete() → halt(204)",
    emptyBranch: "ramo tomado, sem folha → 200 vazio",
    onIntMiss: "onInt() não casa — ramo já tomado → 200 vazio",
    onUsersMiss: "on('users') não casa",
    notFound: "App::handle() → 404 Not Found",
  },
}[locale];

const THEME_KEY = "stem-theme";

function currentTheme() {
  const stored = localStorage.getItem(THEME_KEY);
  return stored === "light" || stored === "dark" ? stored : "system";
}

function applyTheme(mode) {
  if (mode === "light" || mode === "dark") {
    document.documentElement.setAttribute("data-theme", mode);
    localStorage.setItem(THEME_KEY, mode);
  } else {
    document.documentElement.removeAttribute("data-theme");
    localStorage.removeItem(THEME_KEY);
  }
  document.querySelectorAll("[data-theme-value]").forEach((button) => {
    button.setAttribute("aria-pressed", String(button.dataset.themeValue === currentTheme()));
  });
}

applyTheme(currentTheme());
document.querySelectorAll("[data-theme-value]").forEach((button) => {
  button.addEventListener("click", () => applyTheme(button.dataset.themeValue));
});

const nav = document.getElementById("nav");
const menuBtn = document.getElementById("menu");
const logEl = document.getElementById("play-log");

menuBtn?.addEventListener("click", () => {
  nav?.classList.toggle("open");
});

nav?.querySelectorAll("a").forEach((link) => {
  link.addEventListener("click", () => nav.classList.remove("open"));
});

const sections = [...document.querySelectorAll("main section[id]")];
const links = [...document.querySelectorAll(".sidebar a")];

const spy = new IntersectionObserver(
  (entries) => {
    const visible = entries
      .filter((entry) => entry.isIntersecting)
      .sort((a, b) => b.intersectionRatio - a.intersectionRatio)[0];
    if (!visible) return;
    links.forEach((link) => {
      link.classList.toggle("active", link.getAttribute("href") === `#${visible.target.id}`);
    });
  },
  { rootMargin: "-20% 0px -70% 0px", threshold: [0, 0.2, 0.6] },
);

sections.forEach((section) => spy.observe(section));

document.querySelectorAll("[data-copy]").forEach((button) => {
  button.textContent = i18n.copy;
  button.addEventListener("click", async () => {
    const block = button.closest(".prewrap")?.querySelector("pre");
    if (!block) return;
    await navigator.clipboard.writeText(block.innerText);
    button.textContent = i18n.copied;
    setTimeout(() => {
      button.textContent = i18n.copy;
    }, 1200);
  });
});

if (typeof Prism !== "undefined") {
  Prism.highlightAll();
}

function consume(remaining, segment) {
  if (remaining.length === 0) return null;
  if (remaining[0] !== segment) return null;
  return remaining.slice(1);
}

function isInt(segment) {
  if (segment === "0") return 0;
  if (!segment || segment[0] === "0" || !/^\d+$/.test(segment)) return null;
  const n = Number(segment);
  return Number.isSafeInteger(n) ? n : null;
}

function route(method, path) {
  const lines = [];
  let remaining = path.replace(/^\/+|\/+$/g, "").split("/").filter(Boolean);
  let done = false;

  const log = (msg, kind = "") => lines.push({ msg, kind });

  if (!done && remaining.length === 0) {
    log(i18n.rootHit, "hit");
    done = true;
  } else {
    log(i18n.rootMiss, "miss");
  }

  if (!done) {
    const next = consume(remaining, "users");
    if (next) {
      remaining = next;
      log(i18n.onUsers(remaining.join("/")), "hit");
      if (method === "GET" && remaining.length === 0) {
        log(i18n.getList, "hit");
        done = true;
      } else if (method === "POST" && remaining.length === 0) {
        log(i18n.postCreate, "hit");
        done = true;
      } else {
        const id = remaining[0] ? isInt(remaining[0]) : null;
        if (id !== null) {
          remaining = remaining.slice(1);
          log(i18n.onInt(id, remaining.join("/")), "hit");
          if (method === "GET" && remaining.length === 0) {
            log(i18n.getShow(id), "hit");
            done = true;
          } else if (method === "DELETE" && remaining.length === 0) {
            log(i18n.del, "hit");
            done = true;
          } else {
            log(i18n.emptyBranch, "miss");
            done = true;
          }
        } else {
          log(i18n.onIntMiss, "miss");
          done = true;
        }
      }
    } else {
      log(i18n.onUsersMiss, "miss");
    }
  }

  if (!done) log(i18n.notFound, "miss");
  return lines;
}

function renderPlay(method, path) {
  const lines = route(method, path);
  logEl.innerHTML = lines
    .map((line) => `<span class="${line.kind}">${line.msg}</span>`)
    .join("\n");
}

document.querySelectorAll("[data-play]").forEach((button) => {
  button.addEventListener("click", () => {
    document.querySelectorAll("[data-play]").forEach((other) => other.setAttribute("aria-pressed", "false"));
    button.setAttribute("aria-pressed", "true");
    const [method, path] = button.dataset.play.split(" ");
    renderPlay(method, path);
  });
});

renderPlay("GET", "/");
