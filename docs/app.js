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
  button.addEventListener("click", async () => {
    const pre = button.parentElement?.querySelector("pre");
    if (!pre) return;
    await navigator.clipboard.writeText(pre.innerText);
    button.textContent = "copied";
    setTimeout(() => {
      button.textContent = "copy";
    }, 1200);
  });
});

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
    log("root() match → json({ message: StemPHP API })", "hit");
    done = true;
  } else {
    log("root() miss", "miss");
  }

  if (!done) {
    const next = consume(remaining, "users");
    if (next) {
      remaining = next;
      log(`on('users') consume → remaining /${remaining.join("/")}`, "hit");
      if (method === "GET" && remaining.length === 0) {
        log("get() terminal → json([{ id: 1, name: John }])", "hit");
        done = true;
      } else if (method === "POST" && remaining.length === 0) {
        log("post() terminal → json({ id: 2 }, 201)", "hit");
        done = true;
      } else {
        const id = remaining[0] ? isInt(remaining[0]) : null;
        if (id !== null) {
          remaining = remaining.slice(1);
          log(`onInt() capture ${id} → remaining /${remaining.join("/")}`, "hit");
          if (method === "GET" && remaining.length === 0) {
            log(`get() terminal → json({ id: ${id}, name: John })`, "hit");
            done = true;
          } else if (method === "DELETE" && remaining.length === 0) {
            log("delete() → halt(204)", "hit");
            done = true;
          } else {
            log("branch taken, no leaf → 200 empty", "miss");
            done = true;
          }
        } else {
          log("onInt() miss — branch already taken → 200 empty", "miss");
          done = true;
        }
      }
    } else {
      log("on('users') miss", "miss");
    }
  }

  if (!done) log("App::handle() → 404 Not Found", "miss");
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
