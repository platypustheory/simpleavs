/**
 * SimpleAVS front-end - Drupal 10.
 * Modes:
 *  - method = "question": Yes/No buttons
 *  - method = "dob": typed DOB with auto-formatting
 *
 * Settings needed in drupalSettings.simpleavs:
 *   enabled: boolean
 *   method: "question" | "dob"
 *   min_age: number
 *   date_format: "mdy" | "dmy"
 *   endpoints: { token: string, verify: string }
 *   frequency: "never" | "session" | "daily" | "weekly" | "always"
 *   redirects: { success: string?, failure: string? }
 *   strings: {...} and appearance: {...} (optional theming/text)
 */
(function (Drupal, drupalSettings) {
  "use strict";

  // ---------- small helpers ----------
  function el(tag, props, children) {
    props = props || {};
    children = children || [];
    var n = document.createElement(tag);
    if (props["class"]) n.className = props["class"];
    if (props.style) n.style.cssText = props.style;
    if (props.attrs) {
      for (var k in props.attrs) {
        if (Object.prototype.hasOwnProperty.call(props.attrs, k)) {
          n.setAttribute(k, props.attrs[k]);
        }
      }
    }
    if (props.text != null) n.textContent = props.text;
    for (var i = 0; i < children.length; i++) n.appendChild(children[i]);
    return n;
  }

  function getJSON(url) {
    return fetch(url, { credentials: "same-origin", cache: "no-store" }).then(function (r) {
      if (!r.ok) throw new Error("HTTP " + r.status);
      return r.json();
    });
  }

  function postForm(url, data) {
    return fetch(url, {
      method: "POST",
      credentials: "same-origin",
      headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
      body: new URLSearchParams(data).toString(),
    }).then(function (r) {
      var ct = r.headers.get("content-type") || "";
      if (!r.ok) throw new Error("HTTP " + r.status);
      if (ct.indexOf("application/json") !== -1) return r.json();
      return { ok: false, error: "Unexpected response." };
    });
  }

  // ---------- frequency helpers ----------
  var LS_KEY = "simpleavs:lastPass";
  var SS_KEY = "simpleavs:sessionPass";

  function nowMs() { return Date.now(); }
  function ms(days) { return days * 24 * 60 * 60 * 1000; }

  function lastPassMs() {
    var v = localStorage.getItem(LS_KEY);
    return v ? parseInt(v, 10) : 0;
  }

  function shouldPrompt(cfg) {
    var freq = (cfg.frequency || "always").toLowerCase();
    switch (freq) {
      case "never":   return false;
      case "always":  return true;
      case "session": return sessionStorage.getItem(SS_KEY) !== "1";
      case "daily":   return (nowMs() - lastPassMs()) > ms(1);
      case "weekly":  return (nowMs() - lastPassMs()) > ms(7);
      default:        return true;
    }
  }

  function markPassed(freq) {
    var f = (freq || "always").toLowerCase();
    if (f === "session") {
      sessionStorage.setItem(SS_KEY, "1");
    } else if (f === "daily" || f === "weekly") {
      localStorage.setItem(LS_KEY, String(nowMs()));
    }
  }

  function valOr(obj, key, fallback) {
    return obj && obj[key] != null ? obj[key] : fallback;
  }

  function buildBase(cfg) {
    var ap = cfg.appearance || {};
    var overlay = el("div", {
      "class": "simpleavs-overlay",
      style:
        "position:fixed;inset:0;display:flex;align-items:center;justify-content:center;z-index:9999;" +
        "background:" + valOr(ap, "overlay_color", "#000") + ";" +
        "opacity:" + (valOr(ap, "overlay_opacity", 0.5)) + ";"
    });

    var modal = el("div", {
      "class": "simpleavs-modal",
      style:
        "max-width:720px;width:92%;border-radius:16px;box-shadow:0 20px 40px rgba(0,0,0,.2);" +
        "background:" + valOr(ap, "modal_bg", "#fff") + ";" +
        "color:" + valOr(ap, "text_color", "#000") + ";" +
        "padding:24px;"
    });

    var strings = cfg.strings || {};
    var title = el("h2", { text: (strings.modal_title || "Age Verification Required") });
    title.style.marginTop = "0";
    var body = el("div", { "class": "simpleavs-body" });
    var msg = el("div", { "class": "simpleavs-msg", attrs: { "aria-live": "polite" } });
    msg.style.marginTop = "12px";

    modal.appendChild(title);
    modal.appendChild(body);
    modal.appendChild(msg);
    overlay.appendChild(modal);

    return { overlay: overlay, body: body, msg: msg };
  }

  function buildButtonsRow(cfg) {
    var ap = cfg.appearance || {};
    var row = el("div", { style: "display:flex;gap:12px;margin-top:12px;flex-wrap:wrap;" });
    function btn(label) {
      return el("button", {
        "class": "simpleavs-btn",
        text: label,
        attrs: { type: "button" },
        style:
          "border:0;border-radius:12px;padding:12px 18px;cursor:pointer;" +
          "background:" + valOr(ap, "button_bg", "#1e00ff") + ";" +
          "color:" + valOr(ap, "button_text", "#fff") + ";"
      });
    }
    return { row: row, mkBtn: btn };
  }

  function buildQuestionUI(cfg, body) {
    var strings = cfg.strings || {};
    var p = el("p", {
      text: (strings.question_text || "Are you over the age of [age]?").replace("[age]", cfg.min_age || 18)
    });
    var rowMk = buildButtonsRow(cfg);
    var row = rowMk.row;
    var mkBtn = rowMk.mkBtn;
    var yesBtn = mkBtn(strings.yes_button || "Yes");
    var noBtn = mkBtn(strings.no_button || "No");
    row.appendChild(yesBtn);
    row.appendChild(noBtn);
    body.appendChild(p);
    body.appendChild(row);
    return { yesBtn: yesBtn, noBtn: noBtn };
  }

  function placeholderFor(fmt) {
    return (fmt === "dmy") ? "DD/MM/YYYY" : "MM/DD/YYYY";
  }

  // Auto-format typed digits to MM/DD/YYYY or DD/MM/YYYY as user types.
  function autoFormatInput(input, fmt) {
    input.addEventListener("input", function () {
      var digits = (input.value.match(/\d/g) || []).join("").slice(0, 8);
      var out = "";
      if (fmt === "dmy") {
        var d = digits.slice(0, 2);
        var m = digits.slice(2, 4);
        var y = digits.slice(4, 8);
        out = d;
        if (digits.length > 2) out += "/" + m;
        if (digits.length > 4) out += "/" + y;
      } else {
        var m2 = digits.slice(0, 2);
        var d2 = digits.slice(2, 4);
        var y2 = digits.slice(4, 8);
        out = m2;
        if (digits.length > 2) out += "/" + d2;
        if (digits.length > 4) out += "/" + y2;
      }
      input.value = out;
    });
  }

  function buildDobUI(cfg, body) {
    var strings = cfg.strings || {};
    var instr = el("p", { text: (strings.dob_instruction || "Please enter your date of birth to verify your age:") });
    var form = el("form", { attrs: { autocomplete: "off" } });
    form.addEventListener("submit", function (e) { e.preventDefault(); });

    var group = el("div", { style: "display:flex;gap:8px;flex-wrap:wrap;align-items:center;" });
    var input = el("input", { attrs: { type: "text", inputmode: "numeric" } });
    input.style.padding = "10px";
    input.style.borderRadius = "8px";
    input.style.border = "1px solid #ccc";

    var fmt = (cfg.date_format === "dmy") ? "dmy" : "mdy"; // default mdy
    input.placeholder = placeholderFor(fmt);
    input.title = (fmt === "dmy")
      ? "Type DDMMYYYY or DD/MM/YYYY or DD-MM-YYYY"
      : "Type MMDDYYYY or MM/DD/YYYY or MM-DD-YYYY";

    autoFormatInput(input, fmt);

    var mk = buildButtonsRow(cfg).mkBtn;
    var submitBtn = mk(strings.dob_verify_button || "Verify");

    group.appendChild(input);
    group.appendChild(submitBtn);
    form.appendChild(instr);
    form.appendChild(group);
    body.appendChild(form);

    return { input: input, submitBtn: submitBtn, fmt: fmt };
  }

  // Normalize to YYYY-MM-DD given value and format (mdy|dmy).
  function normalizeDob(val, fmt) {
    if (!val) return null;
    val = String(val).trim();
    var digits = val.replace(/[^\d]/g, "");
    if (digits.length !== 8) return null;

    var m, d, y;
    if (fmt === "dmy") {
      d = digits.slice(0, 2);
      m = digits.slice(2, 4);
      y = digits.slice(4, 8);
    } else {
      m = digits.slice(0, 2);
      d = digits.slice(2, 4);
      y = digits.slice(4, 8);
    }

    var Yi = +y, Mi = +m, Di = +d;
    var dt = new Date(Date.UTC(Yi, Mi - 1, Di));
    if (dt.getUTCFullYear() !== Yi || (dt.getUTCMonth() + 1) !== Mi || dt.getUTCDate() !== Di) return null;

    return y + "-" + m + "-" + d;
  }

  // ---------- main behavior ----------
  Drupal.behaviors.simpleavsAgeGate = {
    attach: function (context) {
      var cfg = drupalSettings.simpleavs || {};
      if (!cfg.enabled) return;

      // Respect frequency BEFORE doing anything.
      if (!shouldPrompt(cfg)) return;

      // Use a simple once guard if core.once isn't available in this context.
      var host = document.body;
      if (!host || host.dataset && host.dataset.simpleavsAgegateApplied === "1") return;
      if (host && host.dataset) host.dataset.simpleavsAgegateApplied = "1";

      var built = buildBase(cfg);
      var overlay = built.overlay;
      var body = built.body;
      var msg = built.msg;
      document.body.appendChild(overlay);

      var token = null;
      var ready = false;

      function setBusy(b) {
        var buttons = overlay.querySelectorAll("button");
        for (var i = 0; i < buttons.length; i++) buttons[i].disabled = !!b;
      }
      setBusy(true);

      getJSON(cfg.endpoints && cfg.endpoints.token ? cfg.endpoints.token : "").then(function (j) {
        token = (j && j.token) ? j.token : null;
        ready = !!token;
        if (!ready) throw new Error("No token");
        setBusy(false);
      }).catch(function () {
        msg.textContent = "Could not obtain verification token.";
      });

      function verify(data) {
        if (!ready || !token) {
          msg.textContent = "Invalid token.";
          return;
        }
        setBusy(true);
        var verifyUrl = (cfg.endpoints && cfg.endpoints.verify) ? cfg.endpoints.verify : "";
        // Build payload without spread (ES5-safe)
        var payload = { token: token };
        for (var k in data) if (Object.prototype.hasOwnProperty.call(data, k)) payload[k] = data[k];

        postForm(verifyUrl, payload).then(function (res) {
          if (res && res.ok) {
            if (res.result === "passed") {
              markPassed(cfg.frequency);
              if (cfg.redirects && cfg.redirects.success) { window.location.href = cfg.redirects.success; return; }
              overlay.remove();
              return;
            }
            if (res.result === "denied") {
              if (cfg.redirects && cfg.redirects.failure) { window.location.href = cfg.redirects.failure; return; }
              overlay.remove();
              return;
            }
            overlay.remove(); // unknown but ok:true
            return;
          } else {
            msg.textContent = (res && res.error) ? res.error : "Verification failed.";
            token = null; ready = false;
            return getJSON(cfg.endpoints && cfg.endpoints.token ? cfg.endpoints.token : "").then(function (j2) {
              token = (j2 && j2.token) ? j2.token : null; ready = !!token;
              setBusy(false);
            }).catch(function () { setBusy(false); });
          }
        }).catch(function () {
          msg.textContent = "Verification failed.";
          token = null; ready = false;
          getJSON(cfg.endpoints && cfg.endpoints.token ? cfg.endpoints.token : "").then(function (j3) {
            token = (j3 && j3.token) ? j3.token : null; ready = !!token;
            setBusy(false);
          }).catch(function () { setBusy(false); });
        });
      }

      if ((cfg.method || "question") === "dob") {
        var dobUI = buildDobUI(cfg, body);
        var input = dobUI.input;
        var submitBtn = dobUI.submitBtn;
        var fmt = dobUI.fmt;
        submitBtn.addEventListener("click", function () {
          var norm = normalizeDob(input.value, fmt);
          if (!norm) {
            var strings = cfg.strings || {};
            msg.textContent = (strings.dob_invalid_message || "Please enter a valid date of birth.");
            return;
          }
          verify({ action: "dob", dob: norm });
        });
      } else {
        var q = buildQuestionUI(cfg, body);
        q.yesBtn.addEventListener("click", function () { verify({ action: "yes" }); });
        q.noBtn .addEventListener("click", function () { verify({ action: "no"  }); });
      }
    }
  };
})(Drupal, drupalSettings);
