/* ==========================================================================
   Wisconsin Repeater Alliance — global behaviour
   Progressive enhancement only: every page works without this file.
   Modules: header · nav indicator · mobile menu · search · accordion ·
   reveal (process, ARCS flow) · scrollspy · resource filter ·
   repeater lookup · forms · copy/toast/print · misc
   ========================================================================== */
(function () {
  "use strict";

  var doc = document;
  var root = doc.documentElement;
  var reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)");
  var EASE_OUT = "cubic-bezier(0.23, 1, 0.32, 1)";
  var ORG_EMAIL = "info@wi-ra.org";
  var ARCS_URL = "https://arcsonline.org/";

  function $(sel, ctx) { return (ctx || doc).querySelector(sel); }
  function $$(sel, ctx) { return Array.prototype.slice.call((ctx || doc).querySelectorAll(sel)); }
  function prefersReduced() { return reduceMotion.matches; }
  function debounce(fn, ms) {
    var t;
    return function () {
      var args = arguments, self = this;
      clearTimeout(t);
      t = setTimeout(function () { fn.apply(self, args); }, ms);
    };
  }
  function escapeHTML(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
      return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c];
    });
  }

  /* ---------------------------------------------------------------------
     Header: shadow once the page scrolls
     --------------------------------------------------------------------- */
  function initHeader() {
    var header = $(".wra-header");
    if (!header) return;
    var ticking = false;
    function update() {
      header.setAttribute("data-scrolled", window.scrollY > 8 ? "true" : "false");
      ticking = false;
    }
    window.addEventListener("scroll", function () {
      if (!ticking) { window.requestAnimationFrame(update); ticking = true; }
    }, { passive: true });
    update();
  }

  /* ---------------------------------------------------------------------
     Primary nav: a single indicator that travels between items.
     Moving while visible → ease-in-out. Rests on the current page.
     --------------------------------------------------------------------- */
  function initNavIndicator() {
    var nav = $(".wra-nav");
    if (!nav) return;
    var links = $$(".wra-nav__link", nav);
    var current = links.filter(function (l) { return l.getAttribute("aria-current") === "page"; })[0] || null;
    var indicator = doc.createElement("span");
    indicator.className = "wra-nav__indicator";
    indicator.setAttribute("aria-hidden", "true");
    nav.appendChild(indicator);
    nav.classList.add("has-indicator");

    var INSET = 11.2; // matches .wra-nav__link horizontal padding (0.7rem)

    function moveTo(link, instant) {
      if (!link) { indicator.classList.remove("is-visible"); return; }
      var navBox = nav.getBoundingClientRect();
      var box = link.getBoundingClientRect();
      var x = box.left - navBox.left + INSET;
      var w = Math.max(box.width - INSET * 2, 8);
      if (instant) indicator.style.transition = "none";
      indicator.style.transform = "translateX(" + x + "px) scaleX(" + (w / 100) + ")";
      indicator.classList.add("is-visible");
      if (instant) {
        indicator.getBoundingClientRect();
        indicator.style.transition = "";
      }
    }

    links.forEach(function (link) {
      link.addEventListener("pointerenter", function () { moveTo(link); });
      link.addEventListener("focus", function () { moveTo(link); });
    });
    nav.addEventListener("pointerleave", function () { moveTo(current); });
    nav.addEventListener("focusout", function (e) {
      if (!nav.contains(e.relatedTarget)) moveTo(current);
    });
    window.addEventListener("resize", debounce(function () { moveTo(current, true); }, 120));
    if (doc.fonts && doc.fonts.ready) doc.fonts.ready.then(function () { moveTo(current, true); });
    moveTo(current, true);
  }

  /* ---------------------------------------------------------------------
     Focus trap helper
     --------------------------------------------------------------------- */
  var FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]), select, textarea, [tabindex]:not([tabindex="-1"])';
  function trapFocus(container, extra) {
    function handler(e) {
      if (e.key !== "Tab") return;
      var items = (extra ? [extra] : []).concat($$(FOCUSABLE, container)).filter(function (el) {
        return el.offsetParent !== null || el === extra;
      });
      if (!items.length) return;
      var first = items[0], last = items[items.length - 1];
      if (e.shiftKey && doc.activeElement === first) { e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && doc.activeElement === last) { e.preventDefault(); first.focus(); }
    }
    doc.addEventListener("keydown", handler);
    return function release() { doc.removeEventListener("keydown", handler); };
  }

  /* ---------------------------------------------------------------------
     Mobile menu — panel drops from the header edge (origin: menu button)
     --------------------------------------------------------------------- */
  function initMobileMenu() {
    var button = $(".wra-menu-button");
    var panel = $("#wra-mobile-nav");
    if (!button || !panel) return;
    var release = null;

    function open() {
      panel.classList.add("is-open");
      button.setAttribute("aria-expanded", "true");
      button.querySelector(".wra-menu-button__text").textContent = "Close";
      doc.body.classList.add("is-locked");
      release = trapFocus(panel, button);
      var first = $("a", panel);
      if (first) setTimeout(function () { first.focus(); }, 40);
    }
    function close(returnFocus) {
      panel.classList.remove("is-open");
      button.setAttribute("aria-expanded", "false");
      button.querySelector(".wra-menu-button__text").textContent = "Menu";
      doc.body.classList.remove("is-locked");
      if (release) { release(); release = null; }
      if (returnFocus) button.focus();
    }
    button.addEventListener("click", function () {
      button.getAttribute("aria-expanded") === "true" ? close(false) : open();
    });
    doc.addEventListener("keydown", function (e) {
      if (e.key === "Escape" && button.getAttribute("aria-expanded") === "true") close(true);
    });
    panel.addEventListener("click", function (e) {
      var target = e.target.closest("a, [data-search-open]");
      if (target) close(false);
    });
    window.matchMedia("(min-width: 68.75rem)").addEventListener("change", function (mq) {
      if (mq.matches) close(false);
    });
  }

  /* ---------------------------------------------------------------------
     Site search — a small static index; the dialog scales in from centre
     --------------------------------------------------------------------- */
  var SEARCH_INDEX = [
    { t: "Home", u: "index.html", s: "Wisconsin Repeater Alliance", d: "Amateur radio frequency coordination for Wisconsin.", k: "wra wi-ra home" },
    { t: "Frequency Coordination", u: "coordination.html", s: "Coordination", d: "What coordination is, how to apply and what you will need.", k: "coordinate repeater frequency pair apply what is" },
    { t: "Apply for coordination", u: "coordination.html#apply", s: "Coordination", d: "Start a coordination request in ARCS.", k: "application new repeater request start" },
    { t: "Information you will need", u: "coordination.html#checklist", s: "Coordination", d: "Pre-application checklist: callsign, trustee, coordinates, ERP, antenna height and more.", k: "checklist trustee callsign coordinates latitude longitude band input output tone ctcss erp antenna height haat pattern sponsor printable" },
    { t: "Before you begin", u: "coordination.html#before", s: "Coordination", d: "What to know before starting an application.", k: "requirements specific frequency pair draft" },
    { t: "The coordination process", u: "coordination-process.html", s: "Coordination", d: "Prepare, submit, engineering review, WRA decision and record.", k: "process steps timeline review decision how long" },
    { t: "Modify an existing repeater", u: "coordination-process.html#modification", s: "Coordination process", d: "Site, antenna, power, frequency or mode changes.", k: "modification change relocate power increase antenna height linked" },
    { t: "Renewal and keeping records current", u: "coordination-process.html#renewal", s: "Coordination process", d: "Record reviews, renewals and off-air notices.", k: "renew renewal records off the air verify" },
    { t: "Report interference", u: "coordination-process.html#interference", s: "Coordination process", d: "How WRA helps resolve interference between systems.", k: "interference complaint conflict report" },
    { t: "ARCS — Amateur Radio Coordination System", u: "arcs.html", s: "ARCS", d: "The engineering and operational platform WRA uses for coordination.", k: "arcs platform software propagation interference database" },
    { t: "What ARCS does", u: "arcs.html#functions", s: "ARCS", d: "Propagation modeling, interference studies, database management, application processing and workflows.", k: "longley-rice propagation modeling interference studies erp fcc lookup" },
    { t: "Sign in to ARCS", u: ARCS_URL, s: "arcsonline.org", d: "Opens ARCS in a new tab.", k: "login log in account arcs", ext: true },
    { t: "Find a Repeater", u: "https://arcsonline.org/repeaters/", s: "arcsonline.org", d: "Search the ARCS repeater directory.", k: "directory lookup search repeater callsign frequency city", ext: true },
    { t: "Membership", u: "membership.html", s: "Membership", d: "$20 a year. Join or renew online through Zeffy.", k: "member join renew dues pay zeffy cost price" },
    { t: "Resources", u: "resources.html", s: "Resources", d: "Forms, policies, band plans and technical references.", k: "documents downloads library forms policies bylaws band plan" },
    { t: "Frequently asked questions", u: "resources.html#faq", s: "Resources", d: "Answers about coordination, membership and WRA.", k: "faq questions answers fcc part 97 digital club" },
    { t: "Volunteer", u: "resources.html#volunteer", s: "Resources", d: "Volunteer roles and the interest form.", k: "volunteer help get involved committee board" },
    { t: "About WRA", u: "about.html", s: "About", d: "Who WRA is, mission, governance and history.", k: "about mission vision who we are" },
    { t: "Governance and nonprofit status", u: "about.html#governance", s: "About", d: "Wisconsin nonprofit, 501(c)(3) public charity, nine-member board.", k: "governance 501c3 nonprofit charity ein tax board bylaws" },
    { t: "Board of Directors", u: "about.html#board", s: "About", d: "Nine-member Board of Directors elected under the bylaws.", k: "board directors officers president" },
    { t: "News & Announcements", u: "news.html", s: "News", d: "Official updates from the Wisconsin Repeater Alliance.", k: "news announcements updates" },
    { t: "WRA receives federal 501(c)(3) public charity status", u: "news-501c3-public-charity-status.html", s: "News · September 24, 2026", d: "IRS recognition effective August 17, 2026.", k: "501c3 irs charity tax deductible" },
    { t: "ARCS development update", u: "news-arcs-development-update.html", s: "News · September 12, 2026", d: "ARCS is a working, cloud-based coordination platform.", k: "arcs update azure" },
    { t: "An open letter to the Wisconsin amateur radio community", u: "news-open-letter.html", s: "News · September 11, 2026", d: "Why WRA was formed and what is being built.", k: "open letter war dissolution transition" },
    { t: "Contact WRA", u: "contact.html", s: "Contact", d: "Coordination, membership, ARCS and governance enquiries.", k: "contact email info@wi-ra.org message help" },
    { t: "Privacy Policy", u: "privacy.html", s: "Policies", d: "How WRA handles personal information.", k: "privacy personal information" },
    { t: "Terms of Use", u: "terms.html", s: "Policies", d: "Terms for using this website.", k: "terms use legal" }
  ];

  function initSearch() {
    var dialog = $("#wra-search");
    var openers = $$("[data-search-open]");
    if (!dialog || typeof dialog.showModal !== "function") return;
    openers.forEach(function (b) { b.hidden = false; });
    var input = $(".wra-search__input", dialog);
    var list = $(".wra-search__results", dialog);
    var status = $(".wra-search__status", dialog);
    var active = -1;
    var lastTrigger = null;

    function render(q) {
      q = (q || "").trim().toLowerCase();
      var terms = q.split(/\s+/).filter(Boolean);
      var results = SEARCH_INDEX
        .map(function (item) {
          if (!terms.length) return { item: item, score: 1 };
          var hay = (item.t + " " + item.d + " " + item.k + " " + item.s).toLowerCase();
          var title = item.t.toLowerCase();
          var score = 0;
          for (var i = 0; i < terms.length; i++) {
            if (hay.indexOf(terms[i]) === -1) return null;
            score += title.indexOf(terms[i]) !== -1 ? 3 : 1;
          }
          return { item: item, score: score };
        })
        .filter(Boolean)
        .sort(function (a, b) { return b.score - a.score; })
        .slice(0, terms.length ? 8 : 7);

      active = -1;
      if (!results.length) {
        list.innerHTML = '<li class="wra-search__empty">No pages match “' + escapeHTML(q) + '”. Try “coordination”, “membership” or “ARCS”.</li>';
        status.textContent = "No results";
        return;
      }
      list.innerHTML = results.map(function (r) {
        var ext = r.item.ext ? ' target="_blank" rel="noopener"' : "";
        var extLabel = r.item.ext ? '<span class="wra-visually-hidden"> (opens in a new tab)</span>' : "";
        return '<li><a href="' + r.item.u + '"' + ext + '><span class="wra-meta">' + escapeHTML(r.item.s) +
          '</span><strong>' + escapeHTML(r.item.t) + extLabel + '</strong><span class="wra-search__desc">' + escapeHTML(r.item.d) + "</span></a></li>";
      }).join("");
      status.textContent = results.length + (results.length === 1 ? " result" : " results");
    }

    function setActive(i) {
      var links = $$("a", list);
      if (!links.length) return;
      active = (i + links.length) % links.length;
      links.forEach(function (l, n) { l.classList.toggle("is-active", n === active); });
      links[active].scrollIntoView({ block: "nearest" });
    }

    function open(trigger) {
      lastTrigger = trigger || doc.activeElement;
      dialog.classList.remove("is-closing");
      dialog.showModal();
      input.value = "";
      render("");
      input.focus();
    }
    function close() {
      if (!dialog.open || dialog.classList.contains("is-closing")) return;
      if (prefersReduced()) { dialog.close(); return; }
      dialog.classList.add("is-closing");
      dialog.addEventListener("animationend", function done() {
        dialog.removeEventListener("animationend", done);
        dialog.classList.remove("is-closing");
        dialog.close();
      });
    }
    dialog.addEventListener("close", function () { if (lastTrigger && lastTrigger.focus) lastTrigger.focus(); });
    dialog.addEventListener("cancel", function (e) { e.preventDefault(); close(); });
    dialog.addEventListener("click", function (e) { if (e.target === dialog) close(); });
    $(".wra-search__close", dialog).addEventListener("click", close);
    input.addEventListener("input", function () { render(input.value); });
    input.addEventListener("keydown", function (e) {
      if (e.key === "Escape") { e.preventDefault(); close(); }
      else if (e.key === "ArrowDown") { e.preventDefault(); setActive(active + 1); }
      else if (e.key === "ArrowUp") { e.preventDefault(); setActive(active - 1); }
      else if (e.key === "Enter") {
        var links = $$("a", list);
        var target = links[active >= 0 ? active : 0];
        if (target) { e.preventDefault(); target.click(); }
      }
    });
    $(".wra-search__form", dialog).addEventListener("submit", function (e) { e.preventDefault(); });
    openers.forEach(function (b) { b.addEventListener("click", function () { open(b); }); });
    doc.addEventListener("keydown", function (e) {
      var tag = (e.target.tagName || "").toLowerCase();
      if (e.key === "/" && !dialog.open && tag !== "input" && tag !== "textarea" && tag !== "select" && !e.target.isContentEditable) {
        e.preventDefault();
        open(null);
      }
    });
  }

  /* ---------------------------------------------------------------------
     Accordion — native <details>, with a natural height transition
     --------------------------------------------------------------------- */
  function initAccordions() {
    $$(".wra-accordion").forEach(function (details) {
      var summary = $("summary", details);
      var body = $(".wra-accordion__body", details);
      if (!summary || !body || !body.animate) return;
      var running = null;
      summary.addEventListener("click", function (e) {
        if (prefersReduced()) return;
        e.preventDefault();
        if (running) running.cancel();
        if (!details.open) {
          details.open = true;
          var h = body.scrollHeight;
          running = body.animate(
            [{ height: "0px", opacity: 0 }, { height: h + "px", opacity: 1 }],
            { duration: 240, easing: EASE_OUT }
          );
        } else {
          var from = body.offsetHeight;
          running = body.animate(
            [{ height: from + "px", opacity: 1 }, { height: "0px", opacity: 0 }],
            { duration: 180, easing: EASE_OUT }
          );
          running.onfinish = function () { details.open = false; };
        }
        running.addEventListener("finish", function () { running = null; });
      });
    });
    // Open a question linked by hash
    if (location.hash) {
      var target = doc.getElementById(location.hash.slice(1));
      if (target && target.tagName === "DETAILS") target.open = true;
    }
  }

  /* ---------------------------------------------------------------------
     Reveal: coordination process + ARCS flow activate once, in sequence
     --------------------------------------------------------------------- */
  function onceVisible(el, cb, threshold) {
    if (!("IntersectionObserver" in window)) { cb(); return; }
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) { io.disconnect(); cb(); }
      });
    }, { threshold: threshold || 0.3 });
    io.observe(el);
  }

  function initProcess() {
    $$("[data-process]").forEach(function (proc) {
      var steps = $$(".wra-process__step", proc);
      onceVisible(proc, function () {
        proc.style.setProperty("--progress", "1");
        steps.forEach(function (step, i) {
          setTimeout(function () { step.classList.add("is-active"); }, prefersReduced() ? 0 : 120 + i * 150);
        });
      }, 0.35);
    });
    $$("[data-flow]").forEach(function (flow) {
      var nodes = $$(".wra-flow__node", flow);
      onceVisible(flow, function () {
        nodes.forEach(function (n, i) {
          setTimeout(function () { n.classList.add("is-lit"); }, prefersReduced() ? 0 : i * 220);
        });
      }, 0.4);
    });
  }

  /* ---------------------------------------------------------------------
     Scrollspy for in-page tables of contents
     --------------------------------------------------------------------- */
  function initScrollspy() {
    if (!("IntersectionObserver" in window)) return;
    $$("[data-scrollspy]").forEach(function (toc) {
      var links = $$("a[href^='#']", toc);
      var map = {};
      links.forEach(function (l) {
        var el = doc.getElementById(l.getAttribute("href").slice(1));
        if (el) map[el.id] = { link: l, el: el };
      });
      var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          if (!entry.isIntersecting) return;
          var hit = map[entry.target.id];
          if (!hit) return;
          links.forEach(function (l) { l.classList.remove("is-active"); l.removeAttribute("aria-current"); });
          $$(".wra-stage.is-current").forEach(function (s) { s.classList.remove("is-current"); });
          hit.link.classList.add("is-active");
          hit.link.setAttribute("aria-current", "location");
          if (hit.el.classList.contains("wra-stage")) hit.el.classList.add("is-current");
          // keep the active item visible in horizontally scrolling sub-navs
          var list = hit.link.closest("ul, ol");
          if (list && list.scrollWidth > list.clientWidth) {
            list.scrollTo({ left: hit.link.offsetLeft - 16, behavior: prefersReduced() ? "auto" : "smooth" });
          }
        });
      }, { rootMargin: "-30% 0px -65% 0px" });
      Object.keys(map).forEach(function (id) { io.observe(map[id].el); });
    });
  }

  /* ---------------------------------------------------------------------
     Resource library filter (category chips + text search)
     --------------------------------------------------------------------- */
  function initResources() {
    var lib = $("[data-resource-library]");
    if (!lib) return;
    var chips = $$("[data-filter]", lib);
    var items = $$(".wra-resource", lib);
    var input = $("[data-resource-search]", lib);
    var count = $("[data-resource-count]", lib);
    var empty = $(".wra-resources__empty", lib);
    var filter = "all";

    function apply() {
      var q = (input && input.value || "").trim().toLowerCase();
      var shown = 0;
      items.forEach(function (item) {
        var okCat = filter === "all" || item.getAttribute("data-category") === filter;
        var okText = !q || item.textContent.toLowerCase().indexOf(q) !== -1;
        var show = okCat && okText;
        item.classList.toggle("is-hiding", !show);
        if (show) shown++;
      });
      if (count) count.textContent = shown + (shown === 1 ? " resource" : " resources") + (filter !== "all" ? " in " + chips.filter(function (c) { return c.getAttribute("data-filter") === filter; })[0].getAttribute("data-label") : "");
      if (empty) empty.hidden = shown !== 0;
    }
    function setFilter(value) {
      filter = value;
      chips.forEach(function (c) { c.setAttribute("aria-pressed", c.getAttribute("data-filter") === value ? "true" : "false"); });
      apply();
    }
    chips.forEach(function (c) { c.addEventListener("click", function () { setFilter(c.getAttribute("data-filter")); }); });
    if (input) input.addEventListener("input", debounce(apply, 80));

    var hash = location.hash.slice(1);
    if (hash && chips.some(function (c) { return c.getAttribute("data-filter") === hash; })) {
      setFilter(hash);
      setTimeout(function () { lib.scrollIntoView({ block: "start" }); }, 0);
    } else {
      apply();
    }
  }

  /* ---------------------------------------------------------------------
     Repeater lookup — live preview from the ARCS public directory snapshot.
     The form itself submits to the ARCS directory (authoritative results).
     --------------------------------------------------------------------- */
  function initLookup() {
    var form = $("[data-lookup]");
    if (!form) return;
    var out = $("[data-lookup-results]");
    var statusEl = $("[data-lookup-status]");
    var pulse = $(".wra-lookup__pulse");
    var fullLink = $("[data-lookup-full]");
    var fields = {
      frequency: form.elements.frequency,
      callsign: form.elements.callsign,
      city: form.elements.city
    };
    var data = null;
    var loading = null;

    function load() {
      if (data || loading) return loading;
      loading = fetch(form.getAttribute("data-snapshot"))
        .then(function (r) { if (!r.ok) throw new Error(r.status); return r.json(); })
        .then(function (json) { data = json.records || []; return data; })
        .catch(function () { data = null; loading = null; statusEl.textContent = "Preview unavailable — search opens the ARCS directory."; });
      return loading;
    }

    function params() {
      var p = new URLSearchParams();
      Object.keys(fields).forEach(function (k) { var v = fields[k].value.trim(); if (v) p.set(k, v); });
      return p;
    }

    function run() {
      var f = fields.frequency.value.trim().replace(/[^0-9.]/g, "");
      var c = fields.callsign.value.trim().toUpperCase();
      var city = fields.city.value.trim().toLowerCase();
      var q = params().toString();
      if (fullLink) fullLink.href = "https://arcsonline.org/repeaters/" + (q ? "?" + q : "");
      if (!f && !c && !city) {
        out.innerHTML = "";
        statusEl.textContent = "Type a frequency, callsign or city to preview matches.";
        return;
      }
      if (!data) { statusEl.textContent = "Loading directory…"; (load() || Promise.resolve()).then(run); return; }
      var matches = data.filter(function (r) {
        if (f && String(r.o).indexOf(f) !== 0) return false;
        if (c && String(r.c).toUpperCase().indexOf(c) === -1) return false;
        if (city && String(r.city).toLowerCase().indexOf(city) !== 0) return false;
        return true;
      });
      var shown = matches.slice(0, 6);
      statusEl.textContent = matches.length
        ? matches.length + (matches.length === 1 ? " matching record" : " matching records") + (matches.length > shown.length ? " · showing " + shown.length : "")
        : "No matches in the preview. Try fewer characters, or search ARCS directly.";
      if (pulse && !prefersReduced()) {
        pulse.classList.remove("is-pulsing");
        void pulse.offsetWidth;
        pulse.classList.add("is-pulsing");
      }
      if (!shown.length) { out.innerHTML = ""; return; }
      out.innerHTML = shown.map(function (r) {
        return '<tr class="is-new"><td class="wra-mono"><a href="https://arcsonline.org/repeaters/' + encodeURIComponent(r.id) +
          '/" target="_blank" rel="noopener">' + escapeHTML(r.c) + '<span class="wra-visually-hidden"> (opens in a new tab)</span></a></td><td class="wra-mono">' +
          escapeHTML(r.o) + '</td><td class="wra-mono">' + escapeHTML(r.i || "—") + "</td><td>" + escapeHTML(r.city) + "</td></tr>";
      }).join("");
    }

    statusEl.textContent = "Type a frequency, callsign or city to preview matches.";
    var debounced = debounce(run, 160);
    Object.keys(fields).forEach(function (k) {
      fields[k].addEventListener("focus", load, { once: true });
      fields[k].addEventListener("input", debounced);
    });
  }

  /* ---------------------------------------------------------------------
     Forms — inline validation. With data-endpoint → POST (loading/success/
     error). Without → compose an email to WRA (no backend in this phase).
     --------------------------------------------------------------------- */
  var ICON_OK = '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="10" cy="10" r="8"/><path d="M6.5 10.2l2.4 2.3 4.6-4.9"/></svg>';
  var ICON_ERR = '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="10" cy="10" r="8"/><path d="M10 6v5M10 13.6v.4"/></svg>';

  function validateField(field) {
    var wrap = field.closest(".wra-field") || field.closest(".wra-checkbox");
    if (!wrap) return true;
    var errorEl = $(".wra-field__error", wrap.classList.contains("wra-field") ? wrap : wrap.parentNode);
    var msg = "";
    var v = field.type === "checkbox" ? field.checked : field.value.trim();
    if (field.required && !v) {
      msg = field.getAttribute("data-required-msg") || "This field is required.";
    } else if (field.type === "email" && v && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v)) {
      msg = "Enter an email address like name@example.com.";
    } else if (field.getAttribute("data-callsign") !== null && v && !/^[A-Za-z0-9]{1,3}[0-9][A-Za-z0-9]{0,4}$/.test(v)) {
      msg = "Enter a valid callsign, for example W9ABC.";
    }
    var host = wrap.classList.contains("wra-field") ? wrap : wrap.parentNode;
    host.classList.toggle("has-error", !!msg);
    field.setAttribute("aria-invalid", msg ? "true" : "false");
    if (errorEl) errorEl.lastChild.textContent = msg;
    return !msg;
  }

  function showStatus(form, kind, title, text) {
    var status = $(".wra-form-status", form.parentNode) || doc.createElement("div");
    status.className = "wra-form-status";
    status.setAttribute("role", kind === "error" ? "alert" : "status");
    status.setAttribute("data-kind", kind);
    status.setAttribute("tabindex", "-1");
    status.innerHTML = (kind === "error" ? ICON_ERR : ICON_OK) + "<div><strong>" + escapeHTML(title) + "</strong></div><p>" + text + "</p>";
    if (!status.parentNode) form.parentNode.insertBefore(status, form);
    status.focus({ preventScroll: true });
    status.scrollIntoView({ block: "nearest", behavior: prefersReduced() ? "auto" : "smooth" });
  }

  function initForms() {
    $$("form[data-validate]").forEach(function (form) {
      form.setAttribute("novalidate", "");
      var controls = $$("input, select, textarea", form).filter(function (el) { return el.type !== "submit" && el.type !== "hidden"; });
      controls.forEach(function (el) {
        el.addEventListener("blur", function () { if (el.value || el.getAttribute("aria-invalid") === "true") validateField(el); });
        el.addEventListener("input", function () { if (el.getAttribute("aria-invalid") === "true") validateField(el); });
        el.addEventListener("change", function () { if (el.type === "checkbox") validateField(el); });
      });

      form.addEventListener("submit", function (e) {
        e.preventDefault();
        var firstBad = null;
        controls.forEach(function (el) { if (!validateField(el) && !firstBad) firstBad = el; });
        if (firstBad) {
          showStatus(form, "error", "Please check the highlighted fields", "Some required information is missing or needs correcting.");
          firstBad.focus();
          return;
        }
        var submit = $("[type=submit]", form);
        var endpoint = form.getAttribute("data-endpoint");
        var subjectPrefix = form.getAttribute("data-subject") || "Website enquiry";

        if (endpoint) {
          submit.classList.add("is-loading");
          submit.setAttribute("aria-disabled", "true");
          fetch(endpoint, { method: "POST", body: new FormData(form), headers: { Accept: "application/json" } })
            .then(function (r) { if (!r.ok) throw new Error(r.status); })
            .then(function () {
              form.reset();
              showStatus(form, "success", "Message sent", "Thank you. A member of the WRA team will reply by email.");
            })
            .catch(function () {
              showStatus(form, "error", "Your message could not be sent", 'Please try again, or email <a href="mailto:' + ORG_EMAIL + '">' + ORG_EMAIL + "</a> directly.");
            })
            .then(function () {
              submit.classList.remove("is-loading");
              submit.removeAttribute("aria-disabled");
            });
          return;
        }

        // No endpoint configured: compose an email with the form contents.
        var lines = [];
        var subjectExtra = "";
        controls.forEach(function (el) {
          if (el.type === "checkbox" && el.name === "consent") return;
          var label = el.getAttribute("data-label") || el.name;
          var value = el.type === "checkbox" ? (el.checked ? "Yes" : "") : el.value.trim();
          if (el.name === "subject" && value) subjectExtra = el.options[el.selectedIndex].text;
          if (value) lines.push(label + ": " + value);
        });
        var checked = $$("input[type=checkbox][name='interest']:checked", form).map(function (c) { return c.value; });
        if (checked.length) lines = lines.filter(function (l) { return l.indexOf("interest:") !== 0; }).concat(["Areas of interest: " + checked.join(", ")]);
        var subject = subjectPrefix + (subjectExtra ? " — " + subjectExtra : "");
        var href = "mailto:" + ORG_EMAIL + "?subject=" + encodeURIComponent(subject) + "&body=" + encodeURIComponent(lines.join("\n"));
        window.location.href = href;
        showStatus(form, "success", "Your email app should now open",
          "We have prepared a message to <a href=\"mailto:" + ORG_EMAIL + "\">" + ORG_EMAIL + "</a> with the details you entered — send it from your email app to reach WRA. If nothing opened, email us directly at that address.");
      });
    });

    // Contact page: preselect subject from ?subject=
    var select = $("select[name=subject][data-from-query]");
    if (select) {
      var wanted = new URLSearchParams(location.search).get("subject");
      if (wanted && $$("option", select).some(function (o) { return o.value === wanted; })) select.value = wanted;
    }
  }

  /* ---------------------------------------------------------------------
     Toast, copy-to-clipboard and print
     --------------------------------------------------------------------- */
  var toastEl = null, toastTimer = null;
  function toast(message) {
    if (!toastEl) {
      toastEl = doc.createElement("div");
      toastEl.className = "wra-toast";
      toastEl.setAttribute("role", "status");
      toastEl.setAttribute("aria-live", "polite");
      toastEl.setAttribute("data-state", "hidden");
      doc.body.appendChild(toastEl);
      toastEl.getBoundingClientRect();
    }
    toastEl.innerHTML = ICON_OK + "<span>" + escapeHTML(message) + "</span>";
    toastEl.setAttribute("data-state", "visible");
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () { toastEl.setAttribute("data-state", "hidden"); }, 2600);
  }

  function initUtilities() {
    $$("[data-copy]").forEach(function (btn) {
      btn.hidden = false;
      btn.addEventListener("click", function () {
        var text = btn.getAttribute("data-copy");
        var done = function () { toast(btn.getAttribute("data-copy-message") || "Copied to clipboard"); };
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(text).then(done, function () { window.prompt("Copy:", text); });
        } else {
          window.prompt("Copy:", text);
        }
      });
    });
    $$("[data-print]").forEach(function (btn) {
      btn.hidden = false;
      btn.addEventListener("click", function () { window.print(); });
    });
    $$("[data-year]").forEach(function (el) { el.textContent = String(new Date().getFullYear()); });
  }

  /* ---------------------------------------------------------------------
     Boot
     --------------------------------------------------------------------- */
  function boot() {
    initHeader();
    initNavIndicator();
    initMobileMenu();
    initSearch();
    initAccordions();
    initProcess();
    initScrollspy();
    initResources();
    initLookup();
    initForms();
    initUtilities();
    window.requestAnimationFrame(function () { root.classList.add("is-ready"); });
  }

  if (doc.readyState === "loading") doc.addEventListener("DOMContentLoaded", boot);
  else boot();
})();
