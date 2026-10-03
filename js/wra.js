/* Wisconsin Repeater Alliance: site behaviour
   Progressive enhancement only. Every page reads and navigates without it. */
(function () {
  "use strict";

  var doc = document.documentElement;
  var reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  var finePointer = window.matchMedia("(hover: hover) and (pointer: fine)");
  var $ = function (sel, root) { return (root || document).querySelector(sel); };
  var $$ = function (sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); };

  /* Header: fixed on every page; transparent over the page head, solid once scrolled */
  var hdr = $(".hdr");
  if (hdr) {
    var ticking = false;
    var update = function () {
      hdr.classList.toggle("is-solid", window.scrollY > 8 || doc.classList.contains("menu-open"));
      ticking = false;
    };
    window.addEventListener("scroll", function () {
      if (!ticking) { ticking = true; requestAnimationFrame(update); }
    }, { passive: true });
    update();

    /* Dropdowns: disclosure buttons (click, keyboard), with hover on mice */
    var items = $$(".nav__item", hdr).filter(function (i) { return !!$(".nav__toggle", i); });
    var setItem = function (item, open) {
      item.classList.toggle("is-open", open);
      $(".nav__toggle", item).setAttribute("aria-expanded", String(open));
    };
    var closeAll = function (except) { items.forEach(function (i) { if (i !== except) setItem(i, false); }); };
    items.forEach(function (item) {
      var btn = $(".nav__toggle", item), timer = null;
      btn.addEventListener("click", function () {
        var open = !item.classList.contains("is-open");
        closeAll(item);
        setItem(item, open);
      });
      item.addEventListener("pointerenter", function (e) {
        if (e.pointerType !== "mouse" || !finePointer.matches) return;
        clearTimeout(timer);
        timer = setTimeout(function () { closeAll(item); setItem(item, true); }, 60);
      });
      item.addEventListener("pointerleave", function (e) {
        if (e.pointerType !== "mouse") return;
        clearTimeout(timer);
        timer = setTimeout(function () { setItem(item, false); }, 160);
      });
      item.addEventListener("keydown", function (e) {
        if (e.key === "Escape" && item.classList.contains("is-open")) { setItem(item, false); btn.focus(); }
      });
      item.addEventListener("focusout", function (e) { if (!item.contains(e.relatedTarget)) setItem(item, false); });
    });
    document.addEventListener("click", function (e) { if (!e.target.closest(".nav__item")) closeAll(); });
  }

  /* Mobile drawer: same structure as the header, as collapsible groups ---- */
  var menuBtn = $(".hdr__menu");
  var drawer = document.getElementById("drawer");
  if (menuBtn && drawer) {
    var label = $(".hdr__menu-label", menuBtn);
    var background = [$("main"), $(".footer"), $(".skip-link")];
    var setOpen = function (open) {
      doc.classList.toggle("menu-open", open);
      menuBtn.setAttribute("aria-expanded", String(open));
      label.textContent = open ? "Close" : "Menu";
      drawer.toggleAttribute("inert", !open);
      drawer.setAttribute("aria-hidden", String(!open));
      background.forEach(function (el) { if (el) el.toggleAttribute("inert", open); });
      if (hdr) hdr.classList.toggle("is-solid", open || window.scrollY > 8);
      if (open) {
        var first = $(".drawer__link", drawer);
        if (first) setTimeout(function () { first.focus({ preventScroll: true }); }, 60);
      }
    };
    setOpen(false);
    menuBtn.addEventListener("click", function () { setOpen(menuBtn.getAttribute("aria-expanded") !== "true"); });
    drawer.addEventListener("click", function (e) {
      if (e.target === drawer || e.target.closest("a")) setOpen(false);
    });
    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape" && doc.classList.contains("menu-open")) { setOpen(false); menuBtn.focus(); }
    });
    window.matchMedia("(min-width: 1201px)").addEventListener("change", function (mq) { if (mq.matches) setOpen(false); });
    $$(".drawer__toggle", drawer).forEach(function (b) {
      var sub = document.getElementById(b.getAttribute("aria-controls"));
      var set = function (open) { b.setAttribute("aria-expanded", String(open)); sub.hidden = !open; };
      b.addEventListener("click", function () { set(b.getAttribute("aria-expanded") !== "true"); });
      if ($("[aria-current]", sub)) set(true); // open the group holding the current page
    });
  }

  /* Scroll reveals -------------------------------------------------------- */
  var revealables = $$("[data-reveal]");
  if ("IntersectionObserver" in window && !reduceMotion) {
    // A fully clip-path-masked element never reports as intersecting, so
    // mask reveals are observed through their (unclipped) parent.
    var targets = new Map();
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        (targets.get(entry.target) || []).forEach(function (el) { el.classList.add("is-in"); });
        io.unobserve(entry.target);
      });
    }, { rootMargin: "0px 0px -8% 0px", threshold: 0.04 });
    revealables.forEach(function (el) {
      var watched = el.getAttribute("data-reveal") === "mask" ? el.parentElement : el;
      if (!targets.has(watched)) targets.set(watched, []);
      targets.get(watched).push(el);
      io.observe(watched);
    });
  } else {
    revealables.forEach(function (el) { el.classList.add("is-in"); });
  }

  /* Hero: slideshow ------------------------------------------------------- */
  var hero = $(".hero");
  if (hero) {
    var sets = $$(".hero__frame", hero).map(function (f) { return $$(".hero__slide", f); });
    var count = sets[0].length;
    var cap = $(".hero__cap", hero), num = $(".hero__num", hero), bar = $(".hero__progress i", hero), pauseBtn = $(".hero__pause", hero);
    var interval = parseInt(hero.getAttribute("data-interval"), 10) || 7000;
    var index = 0, timer = null, paused = reduceMotion;
    hero.style.setProperty("--dur", interval + "ms");
    var restartBar = function () { bar.classList.remove("is-running"); void bar.offsetWidth; if (!paused) bar.classList.add("is-running"); };
    /* Slides after the first load once the page has finished loading */
    var hydrate = function () {
      $$("img[data-src]", hero).forEach(function (img) {
        if (img.getAttribute("data-srcset")) img.srcset = img.getAttribute("data-srcset");
        img.src = img.getAttribute("data-src");
        img.removeAttribute("data-src"); img.removeAttribute("data-srcset");
      });
    };
    if (document.readyState === "complete") hydrate(); else window.addEventListener("load", hydrate);
    var show = function (i) {
      index = (i + count) % count;
      sets.forEach(function (set) { set.forEach(function (s, n) { s.classList.toggle("is-active", n === index); }); });
      cap.textContent = sets[0][index].getAttribute("data-caption");
      num.textContent = String(index + 1);
      restartBar();
    };
    var schedule = function () { clearTimeout(timer); if (!paused) timer = setTimeout(function () { show(index + 1); schedule(); }, interval); };
    var setPaused = function (p) {
      paused = p;
      hero.classList.toggle("is-paused", p);
      pauseBtn.setAttribute("aria-label", p ? "Play slideshow" : "Pause slideshow");
      if (p) clearTimeout(timer); else { restartBar(); schedule(); }
    };
    pauseBtn.addEventListener("click", function () { setPaused(!paused); });
    document.addEventListener("visibilitychange", function () { if (document.hidden) clearTimeout(timer); else if (!paused) { restartBar(); schedule(); } });
    setPaused(paused);

    /* The signal lens rests over the headline and follows a mouse pointer.
       It never wanders on its own, and the frame loop sleeps once settled. */
    var title = $(".hero__center .hero__title", hero);
    var lx = 0, ly = 0, tx = 0, ty = 0, rest = { x: 0, y: 0 }, running = false;
    var measure = function () {
      var hr = hero.getBoundingClientRect(), r = title.getBoundingClientRect();
      rest = { x: r.left - hr.left + r.width * 0.72, y: r.top - hr.top + r.height * 0.5 };
    };
    var place = function () { hero.style.setProperty("--lx", lx.toFixed(1) + "px"); hero.style.setProperty("--ly", ly.toFixed(1) + "px"); };
    var frame = function () {
      lx += (tx - lx) * 0.14;
      ly += (ty - ly) * 0.14;
      place();
      if (Math.abs(tx - lx) + Math.abs(ty - ly) > 0.4) requestAnimationFrame(frame);
      else { lx = tx; ly = ty; place(); running = false; }
    };
    var aim = function (x, y) {
      tx = x; ty = y;
      if (reduceMotion) { lx = tx; ly = ty; place(); return; }
      if (!running) { running = true; requestAnimationFrame(frame); }
    };
    var init = function () { measure(); lx = tx = rest.x; ly = ty = rest.y; place(); };
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(init); else init();
    window.addEventListener("resize", function () { measure(); aim(rest.x, rest.y); });
    hero.addEventListener("pointermove", function (e) {
      if (e.pointerType !== "mouse" || !finePointer.matches) return;
      var hr = hero.getBoundingClientRect();
      aim(e.clientX - hr.left, e.clientY - hr.top);
    });
    hero.addEventListener("pointerleave", function (e) { if (e.pointerType === "mouse") aim(rest.x, rest.y); });
  }

  /* Map: tooltip for each repeater mark ----------------------------------- */
  var fig = $(".map__fig");
  if (fig) {
    var tip = $(".map__tip", fig), marks = $(".map__marks", fig);
    marks.addEventListener("pointerover", function (e) {
      var m = e.target.closest("polygon");
      if (!m) return;
      var fr = fig.getBoundingClientRect(), r = m.getBoundingClientRect();
      tip.innerHTML = "";
      var b = document.createElement("b"); b.textContent = m.getAttribute("data-c");
      var f = document.createElement("span"); f.textContent = m.getAttribute("data-f") + " MHz output";
      var c = document.createElement("span"); c.textContent = m.getAttribute("data-city");
      tip.append(b, f, c);
      tip.style.left = r.left - fr.left + r.width / 2 + "px";
      tip.style.top = r.top - fr.top + "px";
      tip.classList.add("is-on");
    });
    marks.addEventListener("pointerout", function (e) { if (e.target.closest("polygon")) tip.classList.remove("is-on"); });
  }

  /* FAQ: filter questions as you type ------------------------------------ */
  var faq = $(".faq");
  if (faq) {
    var input = $(".faq__filter input", faq), countEl = $(".faq__count", faq), empty = $(".faq__empty", faq);
    var items = $$(".qa", faq), groups = $$(".faq__group", faq);
    var update = function () {
      var q = input.value.trim().toLowerCase(), shown = 0;
      items.forEach(function (qa) {
        var hit = !q || qa.textContent.toLowerCase().indexOf(q) !== -1;
        qa.hidden = !hit;
        if (hit) shown++;
        if (q && hit) qa.open = true;
      });
      groups.forEach(function (g) { g.hidden = !$$(".qa", g).some(function (qa) { return !qa.hidden; }); });
      countEl.textContent = q ? shown + " of " + items.length + " questions" : items.length + " questions";
      empty.hidden = shown !== 0;
    };
    input.addEventListener("input", update);
    update();
    // Open a question linked by hash, e.g. resources.html#faq-fcc
    var target = location.hash && document.getElementById(location.hash.slice(1));
    if (target && target.classList.contains("qa")) target.open = true;
  }

  /* Pre-application checklist: remember ticks, print on its own ---------- */
  var checklist = $(".checklist");
  if (checklist) {
    var key = "wra-checklist";
    var boxes = $$("input[type=checkbox]", checklist);
    var saved = [];
    try { saved = JSON.parse(localStorage.getItem(key) || "[]"); } catch (e) { saved = []; }
    boxes.forEach(function (b, i) {
      b.checked = saved.indexOf(i) !== -1;
      b.addEventListener("change", function () {
        var on = boxes.map(function (x, n) { return x.checked ? n : -1; }).filter(function (n) { return n !== -1; });
        try { localStorage.setItem(key, JSON.stringify(on)); } catch (e) { /* storage unavailable */ }
      });
    });
    $$("[data-print-checklist]").forEach(function (btn) {
      btn.addEventListener("click", function () {
        document.body.classList.add("print-checklist");
        window.print();
      });
    });
    window.addEventListener("afterprint", function () { document.body.classList.remove("print-checklist"); });
    $$("[data-clear-checklist]").forEach(function (btn) {
      btn.addEventListener("click", function () {
        boxes.forEach(function (b) { b.checked = false; });
        try { localStorage.removeItem(key); } catch (e) { /* storage unavailable */ }
      });
    });
  }

  /* Forms: validate inline; POST when an endpoint is set, else compose email */
  $$("form[data-form]").forEach(function (form) {
    var status = $(".form__status", form);
    // Preselect a topic from ?topic= (e.g. contact.html?topic=interference)
    var topic = new URLSearchParams(location.search).get("topic");
    var sel = $("select[name=subject]", form);
    if (topic && sel) {
      $$("option", sel).forEach(function (o) { if (o.getAttribute("data-topic") === topic) sel.value = o.value; });
    }
    var fieldOf = function (el) { return el.closest(".field"); };
    var setError = function (el, msg) {
      var f = fieldOf(el);
      if (!f) return;
      var err = $(".field__error", f);
      f.classList.toggle("is-invalid", !!msg);
      if (err) err.textContent = msg || "";
      el.setAttribute("aria-invalid", msg ? "true" : "false");
    };
    var check = function (el) {
      if (el.type === "checkbox" && el.required) { setError(el, el.checked ? "" : "Please confirm to continue."); return el.checked; }
      if (el.required && !el.value.trim()) { setError(el, "This field is required."); return false; }
      if (el.type === "email" && el.value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(el.value)) { setError(el, "Enter an email address like name@example.com."); return false; }
      setError(el, "");
      return true;
    };
    $$("input, select, textarea", form).forEach(function (el) {
      el.addEventListener("blur", function () { if (el.value || el.required) check(el); });
      el.addEventListener("input", function () { if (fieldOf(el) && fieldOf(el).classList.contains("is-invalid")) check(el); });
    });
    form.addEventListener("submit", function (e) {
      e.preventDefault();
      var fields = $$("input:not([type=hidden]), select, textarea", form).filter(function (el) { return el.required || el.type === "email"; });
      var bad = fields.filter(function (el) { return !check(el); });
      status.className = "form__status";
      status.textContent = "";
      if (bad.length) {
        status.classList.add("is-err");
        status.textContent = "Please correct the highlighted " + (bad.length === 1 ? "field" : bad.length + " fields") + ".";
        bad[0].focus();
        return;
      }
      var data = new FormData(form);
      var endpoint = form.getAttribute("data-endpoint");
      if (endpoint) {
        var btn = $("button[type=submit]", form);
        btn.disabled = true;
        fetch(endpoint, { method: "POST", body: data, headers: { Accept: "application/json" } })
          .then(function (r) { if (!r.ok) throw new Error(r.status); form.reset(); status.classList.add("is-ok"); status.textContent = form.getAttribute("data-success") || "Thank you. Your message has been sent."; })
          .catch(function () { status.classList.add("is-err"); status.textContent = "Sorry, the message could not be sent. Please email info@wi-ra.org."; })
          .then(function () { btn.disabled = false; });
        return;
      }
      // No endpoint yet: compose an email to WRA with the details filled in.
      var lines = [];
      data.forEach(function (v, k) { if (v && k.indexOf("_") !== 0) lines.push(k.charAt(0).toUpperCase() + k.slice(1).replace(/-/g, " ") + ": " + v); });
      var subject = (data.get("subject") || form.getAttribute("data-subject") || "Website enquiry") + (data.get("callsign") ? " (" + data.get("callsign") + ")" : "");
      location.href = "mailto:info@wi-ra.org?subject=" + encodeURIComponent(subject) + "&body=" + encodeURIComponent(lines.join("\n"));
      status.classList.add("is-ok");
      status.textContent = "Your email app should open with the message ready to send to info@wi-ra.org.";
    });
  });

  /* Zeffy membership form in an accessible dialog ------------------------
     Opens Zeffy's own embed form (the URL in zeffy-form-link) in a native
     <dialog>, using the same open/close messages as Zeffy's embed script,
     without that script's preloaded iframe. The href stays as the fallback. */
  var zeffyLinks = $$("[zeffy-form-link]");
  if (zeffyLinks.length && typeof HTMLDialogElement === "function") {
    var dlg = null, frame = null, opener = null;
    var openMsg = function () { if (frame && frame.contentWindow) frame.contentWindow.postMessage({ id: "zeffy-iframe", open: true }, "*"); };
    var build = function (src) {
      dlg = document.createElement("dialog");
      dlg.className = "zeffy";
      dlg.setAttribute("aria-label", "Membership payment form");
      var close = document.createElement("button");
      close.type = "button";
      close.className = "zeffy__close";
      close.setAttribute("aria-label", "Close the payment form");
      close.innerHTML = "<span aria-hidden=\"true\">&times;</span>";
      frame = document.createElement("iframe");
      frame.title = "WRA membership form, powered and secured by Zeffy";
      frame.allow = "payment";
      frame.src = src + (src.indexOf("?") === -1 ? "?" : "&") + "cachebust=" + Date.now();
      frame.addEventListener("load", openMsg);
      dlg.append(close, frame);
      document.body.appendChild(dlg);
      close.addEventListener("click", function () { dlg.close(); });
      dlg.addEventListener("click", function (e) { if (e.target === dlg) dlg.close(); });
      dlg.addEventListener("close", function () { if (opener) opener.focus(); });
      window.addEventListener("message", function (e) { if (e.data && e.data.id === "zeffy-iframe" && e.data.close) dlg.close(); });
    };
    var warm = function () {
      if (document.querySelector('link[href="https://www.zeffy.com"]')) return;
      var l = document.createElement("link"); l.rel = "preconnect"; l.href = "https://www.zeffy.com"; document.head.appendChild(l);
    };
    zeffyLinks.forEach(function (a) {
      a.addEventListener("pointerenter", warm, { once: true });
      a.addEventListener("focus", warm, { once: true });
      a.addEventListener("click", function (e) {
        e.preventDefault();
        opener = a;
        if (!dlg) build(a.getAttribute("zeffy-form-link")); else openMsg();
        dlg.showModal();
      });
    });
  }

  /* Articles: copy link ---------------------------------------------------- */
  $$("[data-copy-link]").forEach(function (btn) {
    btn.addEventListener("click", function () {
      var label = $("span", btn), done = function () { label.textContent = "Link copied"; setTimeout(function () { label.textContent = "Copy link"; }, 2200); };
      if (navigator.clipboard) navigator.clipboard.writeText(location.href).then(done, function () { prompt("Copy this link:", location.href); });
      else prompt("Copy this link:", location.href);
    });
  });
})();
