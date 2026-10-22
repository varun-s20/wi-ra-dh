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
    var navItems = $$(".nav__item", hdr).filter(function (i) { return !!$(".nav__toggle", i); });
    var setItem = function (item, open) {
      item.classList.toggle("is-open", open);
      $(".nav__toggle", item).setAttribute("aria-expanded", String(open));
    };
    var closeAll = function (except) { navItems.forEach(function (i) { if (i !== except) setItem(i, false); }); };
    navItems.forEach(function (item) {
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
      // a link to a section of the current page does not reload it, so close the panel
      $$(".nav__panel a", item).forEach(function (a) { a.addEventListener("click", function () { setItem(item, false); }); });
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

  /* Hero: background images cross-fade; reduced-motion users get a still image. */
  var hero = $(".hero");
  if (hero) {
    var slides = $$(".hero__slide", hero);
    var interval = parseInt(hero.getAttribute("data-interval"), 10) || 7000;
    var index = 0, timer = null, paused = reduceMotion;
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
      index = (i + slides.length) % slides.length;
      slides.forEach(function (s, n) { s.classList.toggle("is-active", n === index); });
    };
    var schedule = function () { clearTimeout(timer); if (!paused) timer = setTimeout(function () { show(index + 1); schedule(); }, interval); };
    document.addEventListener("visibilitychange", function () { if (document.hidden) clearTimeout(timer); else if (!paused) schedule(); });
    schedule();
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
    var faqItems = $$(".qa", faq), groups = $$(".faq__group", faq);
    var filterFaq = function () {
      var q = input.value.trim().toLowerCase(), shown = 0;
      faqItems.forEach(function (qa) {
        var hit = !q || qa.textContent.toLowerCase().indexOf(q) !== -1;
        qa.hidden = !hit;
        if (hit) shown++;
        if (q && hit) qa.open = true;
      });
      groups.forEach(function (g) { g.hidden = !$$(".qa", g).some(function (qa) { return !qa.hidden; }); });
      countEl.textContent = q ? shown + " of " + faqItems.length + " questions" : faqItems.length + " questions";
      empty.hidden = shown !== 0;
    };
    input.addEventListener("input", filterFaq);
    filterFaq();
    // Open a question linked by hash, e.g. resources.html#faq-fcc
    var target = location.hash && document.getElementById(location.hash.slice(1));
    if (target && target.classList.contains("qa")) target.open = true;
  }

  /* Pre-application checklist: remember ticks, print on its own ---------- */
  var checklist = $(".checklist");
  if (checklist) {
    var key = "wra-checklist-v2";
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

  /* Clubs: the Radio Clubs & Sponsors directory (12 to a page, A–Z or Z–A across all pages).
     The A–Z / Z–A switch and the page links are ordinary links that work without this script;
     here they swap the cards in place instead of reloading:
       - cards on both pages glide to their new place, others fade in and out (FLIP);
       - if the next page takes longer than a moment, skeleton cards stand in for it;
       - a logo plate shimmers until its image has arrived;
       - any failure falls back to a normal page load, and reduced motion skips the movement. */
  var clubsDir = $("[data-clubs-dir]");
  var clubsList = clubsDir && $(".clubs", clubsDir);
  var clubsFrame = clubsList && clubsList.parentNode;
  if (clubsList && window.fetch && window.Promise && window.DOMParser && window.history && history.pushState) {
    var clubsStatus = $("[data-clubs-status]", clubsDir);
    var clubsHeading = $("#dir-h", clubsDir);
    var clubsCache = {};     // page address -> Promise of its HTML
    var clubsSeq = 0;        // the newest request wins
    var clubsMoving = [];    // animations in flight
    var clubsPushed = false; // true once this script has added a history entry
    var clubsMotion = !reduceMotion && !!clubsList.animate;
    var clubsSkeleton = '<li class="club club--skeleton" aria-hidden="true"><div class="club__logo"></div><div class="club__body"><div class="club__name--sk"><span class="sk sk--line"></span><span class="sk sk--line sk--short"></span></div><span class="sk sk--link"></span></div></li>';

    /* A logo plate shimmers until its image has loaded (lazy images that are still off-screen count as loading). */
    var clubsWatchLogos = function (root) {
      $$(".club__logo img", root).forEach(function (img) {
        if (img.complete) return;
        var plate = img.parentNode;
        plate.classList.add("is-loading");
        var done = function () { plate.classList.remove("is-loading"); };
        img.addEventListener("load", done, { once: true });
        img.addEventListener("error", done, { once: true });
      });
    };
    clubsWatchLogos(clubsList);

    var clubsLoad = function (url) {
      var key = url.split("#")[0];
      if (!clubsCache[key]) {
        clubsCache[key] = fetch(key, { credentials: "same-origin", headers: { Accept: "text/html" } }).then(function (r) {
          if (!r.ok) throw new Error("HTTP " + r.status);
          return r.text();
        });
        clubsCache[key].catch(function () { delete clubsCache[key]; });
      }
      return clubsCache[key];
    };
    var clubsTimeout = function (promise, ms) {
      return new Promise(function (resolve, reject) {
        var t = setTimeout(function () { reject(new Error("timeout")); }, ms);
        promise.then(function (v) { clearTimeout(t); resolve(v); }, function (e) { clearTimeout(t); reject(e); });
      });
    };
    var clubsCards = function () { return $$(".club:not(.club--leaving)", clubsList); };
    var clubsNear = function (r) { return r.bottom > -200 && r.top < window.innerHeight + 200; };

    var clubsShowSkeleton = function () {
      var count = clubsCards().length || Number(clubsDir.getAttribute("data-per")) || 12;
      clubsMoving.forEach(function (a) { a.cancel(); });
      clubsMoving = [];
      clubsList.setAttribute("aria-busy", "true");
      clubsList.innerHTML = new Array(count + 1).join(clubsSkeleton);
      if (clubsStatus) clubsStatus.textContent = "Loading listings";
    };

    /* Put the fetched directory in place of the current one. */
    var clubsApply = function (fresh) {
      var freshList = $(".clubs", fresh);
      var oldCards = clubsCards();
      var oldRects = oldCards.map(function (c) { return c.getBoundingClientRect(); }); // where they are now, mid-move included
      var listBox = clubsList.getBoundingClientRect();
      var oldHeight = clubsFrame.getBoundingClientRect().height;
      clubsMoving.forEach(function (a) { a.cancel(); });
      clubsMoving = [];

      var byId = {}, kept = {};
      oldCards.forEach(function (c) { var id = c.getAttribute("data-club"); if (id) byId[id] = c; });
      var incoming = $$(".club", freshList).map(function (c) {
        var id = c.getAttribute("data-club");
        if (id && byId[id]) { kept[id] = true; return { el: byId[id], old: oldCards.indexOf(byId[id]) }; } // same card: keep its loaded logo
        return { el: document.importNode(c, true), old: -1 };
      });
      var leaving = oldCards.filter(function (c) { return !kept[c.getAttribute("data-club")]; }).map(function (c) {
        var r = oldRects[oldCards.indexOf(c)];
        var ghost = c.cloneNode(true);
        ghost.classList.add("club--leaving");
        ghost.removeAttribute("data-club");
        ghost.setAttribute("aria-hidden", "true");
        ghost.setAttribute("inert", "");
        ghost.style.cssText = "top:" + (r.top - listBox.top) + "px;left:" + (r.left - listBox.left) + "px;width:" + r.width + "px;height:" + r.height + "px";
        return ghost;
      });

      clubsList.innerHTML = "";
      incoming.forEach(function (c) { clubsList.appendChild(c.el); });
      leaving.forEach(function (g) { clubsList.appendChild(g); });
      clubsList.removeAttribute("aria-busy");
      clubsWatchLogos(clubsList);

      /* count, sort switch and page data, updated in place so the switch's thumb slides */
      $(".clubs-count", clubsDir).textContent = $(".clubs-count", fresh).textContent;
      var sortNow = $("[data-clubs-sort]", clubsDir), sortNew = $("[data-clubs-sort]", fresh);
      sortNow.setAttribute("data-current", sortNew.getAttribute("data-current"));
      var sortLinks = $$("a", sortNew);
      $$("a", sortNow).forEach(function (a, i) {
        a.setAttribute("href", sortLinks[i].getAttribute("href"));
        if (sortLinks[i].hasAttribute("aria-current")) a.setAttribute("aria-current", "true"); else a.removeAttribute("aria-current");
      });
      ["data-order", "data-page", "data-pages", "data-total"].forEach(function (k) { clubsDir.setAttribute(k, fresh.getAttribute(k)); });
      var pagerNow = $(".clubs-pager", clubsDir), pagerNew = $(".clubs-pager", fresh);
      if (pagerNew) {
        pagerNew = document.importNode(pagerNew, true);
        if (pagerNow) pagerNow.parentNode.replaceChild(pagerNew, pagerNow); else clubsFrame.parentNode.insertBefore(pagerNew, clubsFrame.nextSibling);
      } else if (pagerNow) {
        pagerNow.parentNode.removeChild(pagerNow);
      }

      if (!clubsMotion) {
        leaving.forEach(function (g) { if (g.parentNode) g.parentNode.removeChild(g); });
        return;
      }
      var enter = 0, ease = "cubic-bezier(0.22, 1, 0.36, 1)";
      incoming.forEach(function (c, i) {
        var box = c.el.getBoundingClientRect();
        if (c.old > -1) { // on both pages: glide from the old place to the new one
          var was = oldRects[c.old], dx = was.left - box.left, dy = was.top - box.top;
          if ((!dx && !dy) || (!clubsNear(was) && !clubsNear(box))) return; // off-screen both ways: no need to watch it travel
          clubsMoving.push(c.el.animate([{ transform: "translate(" + dx + "px," + dy + "px)" }, { transform: "none" }], { duration: 700, delay: Math.min(i * 18, 180), easing: ease, fill: "backwards" }));
        } else if (clubsNear(box)) { // new to this page: fade up
          clubsMoving.push(c.el.animate([{ opacity: 0, transform: "translateY(14px)" }, { opacity: 1, transform: "none" }], { duration: 520, delay: 100 + Math.min(enter++ * 35, 280), easing: ease, fill: "backwards" }));
        }
      });
      leaving.forEach(function (g) { // gone from this page: fade out where it stood
        var a = g.animate([{ opacity: 1 }, { opacity: 0 }], { duration: 200, easing: "ease-out", fill: "forwards" });
        a.onfinish = function () { if (g.parentNode) g.parentNode.removeChild(g); };
        clubsMoving.push(a);
      });
      var newHeight = clubsFrame.getBoundingClientRect().height; // so the page below eases to its new place
      if (Math.abs(newHeight - oldHeight) > 1) {
        clubsFrame.style.overflow = "clip";
        var grow = clubsFrame.animate([{ height: oldHeight + "px" }, { height: newHeight + "px" }], { duration: 700, easing: ease });
        grow.onfinish = grow.oncancel = function () { clubsFrame.style.overflow = ""; };
        clubsMoving.push(grow);
      }
    };

    var clubsGo = function (url, opts) {
      var mine = ++clubsSeq, timer = setTimeout(function () { if (mine === clubsSeq) clubsShowSkeleton(); }, 160);
      var page = clubsLoad(url);
      clubsTimeout(page, 10000).then(function (text) {
        clearTimeout(timer);
        if (mine !== clubsSeq) return; // a newer click has taken over
        var doc = new DOMParser().parseFromString(text, "text/html");
        var fresh = $("[data-clubs-dir]", doc);
        if (!fresh || !$(".clubs", fresh) || !$("[data-clubs-sort]", fresh)) throw new Error("not a directory page");
        clubsApply(fresh);
        document.title = doc.title;
        if (opts.push) { history.pushState({ clubs: 1 }, "", url); clubsPushed = true; }
        var pages = Number(fresh.getAttribute("data-pages")), now = Number(fresh.getAttribute("data-page"));
        var order = fresh.getAttribute("data-order") === "za" ? "Z to A" : "A to Z";
        if (clubsStatus) clubsStatus.textContent = (pages > 1 ? "Page " + now + " of " + pages + ", " : "") + "sorted " + order;
        if (opts.pager) {
          clubsHeading.focus({ preventScroll: true });
          clubsDir.scrollIntoView({ behavior: reduceMotion ? "auto" : "smooth", block: "start" });
        }
      }).catch(function () {
        clearTimeout(timer);
        if (mine === clubsSeq) window.location.href = url; // anything unexpected: an ordinary page load
      });
    };

    var clubsLink = function (e) {
      var a = e.target.closest ? e.target.closest("a") : null;
      if (!a || !clubsDir.contains(a)) return null;
      var sorting = !!a.closest("[data-clubs-sort]"), paging = !!a.closest(".clubs-pager");
      return (sorting || paging) ? { a: a, pager: paging } : null;
    };
    clubsDir.addEventListener("click", function (e) {
      var hit = clubsLink(e);
      if (!hit || e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
      e.preventDefault();
      if (hit.a.getAttribute("aria-current")) return; // already showing this one
      clubsGo(hit.a.href, { push: true, pager: hit.pager });
    });
    /* warm the next page as soon as a link is pointed at, so it is usually there before the click lands */
    var clubsWarm = function (e) {
      var hit = clubsLink(e);
      if (hit && !hit.a.getAttribute("aria-current")) clubsLoad(hit.a.href).catch(function () {});
    };
    clubsDir.addEventListener("pointerover", clubsWarm);
    clubsDir.addEventListener("focusin", clubsWarm);
    clubsDir.addEventListener("touchstart", clubsWarm, { passive: true });
    window.addEventListener("popstate", function () {
      if (clubsPushed) clubsGo(window.location.href, { push: false, pager: false });
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
