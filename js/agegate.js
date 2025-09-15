/**
 * SimpleAVS front-end — Drupal 10.
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
 *   redirects: { success?: string, failure?: string }
 *   strings: {...} and appearance: {...} (optional theming/text)
 */
(function (Drupal, drupalSettings) {
  "use strict";

  // ---------- small helpers ----------
  function el(tag, props = {}, children = []) {
    const n = document.createElement(tag);
    if (props.class) n.className = props.class;
    if (props.style) n.style.cssText = props.style;
    if (props.attrs) for (const [k,v] of Object.entries(props.attrs)) n.setAttribute(k, v);
    if (props.text) n.textContent = props.text;
    for (const c of children) n.appendChild(c);
    return n;
  }

  async function getJSON(url) {
    const r = await fetch(url, { credentials: "same-origin", cache: "no-store" });
    if (!r.ok) throw new Error("HTTP " + r.status);
    return r.json();
  }

  async function postForm(url, data) {
    const r = await fetch(url, {
      method: "POST",
      credentials: "same-origin",
      headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
      body: new URLSearchParams(data).toString(),
    });
    const ct = r.headers.get("content-type") || "";
    if (!r.ok) throw new Error("HTTP " + r.status);
    if (ct.includes("application/json")) return r.json();
    return { ok:false, error:"Unexpected response." };
  }

  // ---------- frequency helpers ----------
  const LS_KEY = "simpleavs:lastPass";
  const SS_KEY = "simpleavs:sessionPass";

  function nowMs() { return Date.now(); }
  function ms(days) { return days * 24 * 60 * 60 * 1000; }

  function lastPassMs() {
    const v = localStorage.getItem(LS_KEY);
    return v ? parseInt(v, 10) : 0;
  }

  function shouldPrompt(cfg) {
    const freq = (cfg.frequency || "always").toLowerCase();
    switch (freq) {
      case "never":
        return false;
      case "always":
        return true;
      case "session":
        return sessionStorage.getItem(SS_KEY) !== "1";
      case "daily":
        return (nowMs() - lastPassMs()) > ms(1);
      case "weekly":
        return (nowMs() - lastPassMs()) > ms(7);
      default:
        return true;
    }
  }

  function markPassed(freq) {
    switch ((freq || "always").toLowerCase()) {
      case "session":
        sessionStorage.setItem(SS_KEY, "1");
        break;
      case "daily":
        localStorage.setItem(LS_KEY, String(nowMs()));
        break;
      case "weekly":
        localStorage.setItem(LS_KEY, String(nowMs()));
        break;
      // always/never: nothing to persist
    }
  }

  function buildBase(cfg) {
    const overlay = el("div", {
      class: "simpleavs-overlay",
      style:
        "position:fixed;inset:0;display:flex;align-items:center;justify-content:center;z-index:9999;" +
        `background:${cfg.appearance?.overlay_color || "#000"};opacity:${cfg.appearance?.overlay_opacity ?? 0.5};`
    });

    const modal = el("div", {
      class: "simpleavs-modal",
      style:
        "max-width:720px;width:92%;border-radius:16px;box-shadow:0 20px 40px rgba(0,0,0,.2);" +
        `background:${cfg.appearance?.modal_bg || "#fff"};color:${cfg.appearance?.text_color || "#000"};` +
        "padding:24px;"
    });

    const title = el("h2", { text: (cfg.strings?.modal_title || "Age Verification Required") });
    title.style.marginTop = "0";
    const body = el("div", { class: "simpleavs-body" });
    const msg  = el("div", { class: "simpleavs-msg", attrs: { "aria-live":"polite" } });
    msg.style.marginTop = "12px";

    modal.appendChild(title);
    modal.appendChild(body);
    modal.appendChild(msg);
    overlay.appendChild(modal);

    return { overlay, body, msg };
  }

  function buildButtonsRow(cfg) {
    const row = el("div", { style: "display:flex;gap:12px;margin-top:12px;flex-wrap:wrap;" });
    function btn(label) {
      return el("button", {
        class: "simpleavs-btn",
        text: label,
        attrs: { type: "button" },
        style:
          "border:0;border-radius:12px;padding:12px 18px;cursor:pointer;" +
          `background:${cfg.appearance?.button_bg || "#1e00ff"};` +
          `color:${cfg.appearance?.button_text || "#fff"};`
      });
    }
    return { row, mkBtn: btn };
  }

  function buildQuestionUI(cfg, body) {
    const p = el("p", { text: (cfg.strings?.question_text || "Are you over the age of [age]?").replace("[age]", cfg.min_age || 18) });
    const { row, mkBtn } = buildButtonsRow(cfg);
    const yesBtn = mkBtn(cfg.strings?.yes_button || "Yes");
    const noBtn  = mkBtn(cfg.strings?.no_button  || "No");
    row.appendChild(yesBtn);
    row.appendChild(noBtn);
    body.appendChild(p);
    body.appendChild(row);
    return { yesBtn, noBtn };
  }

  function placeholderFor(fmt) {
    return (fmt === "dmy") ? "DD/MM/YYYY" : "MM/DD/YYYY";
  }

  // Auto-format typed digits to MM/DD/YYYY or DD/MM/YYYY as user types.
  function autoFormatInput(input, fmt) {
    input.addEventListener("input", () => {
      // keep digits only, cap at 8 (MMDDYYYY or DDMMYYYY)
      let digits = (input.value.match(/\d/g) || []).join("").slice(0, 8);
      let out = "";
      if (fmt === "dmy") {
        const d = digits.slice(0, 2);
        const m = digits.slice(2, 4);
        const y = digits.slice(4, 8);
        out = d;
        if (digits.length > 2) out += "/" + m;
        if (digits.length > 4) out += "/" + y;
      } else {
        const m = digits.slice(0, 2);
        const d = digits.slice(2, 4);
        const y = digits.slice(4, 8);
        out = m;
        if (digits.length > 2) out += "/" + d;
        if (digits.length > 4) out += "/" + y;
      }
      input.value = out;
    });
  }

  function buildDobUI(cfg, body) {
    const instr = el("p", { text: (cfg.strings?.dob_instruction || "Please enter your date of birth to verify your age:") });
    const form = el("form", { attrs: { autocomplete: "off" } });
    form.addEventListener("submit", (e)=>e.preventDefault());

    const group = el("div", { style: "display:flex;gap:8px;flex-wrap:wrap;align-items:center;" });
    const input = el("input", { attrs: { type: "text", inputmode: "numeric" } });
    input.style.padding = "10px";
    input.style.borderRadius = "8px";
    input.style.border = "1px solid #ccc";

    const fmt = (cfg.date_format === "dmy") ? "dmy" : "mdy"; // default mdy
    input.placeholder = placeholderFor(fmt);
    input.title = (fmt === "dmy")
      ? "Type DDMMYYYY or DD/MM/YYYY or DD-MM-YYYY"
      : "Type MMDDYYYY or MM/DD/YYYY or MM-DD-YYYY";

    autoFormatInput(input, fmt);

    const { mkBtn } = buildButtonsRow(cfg);
    const submitBtn = mkBtn(cfg.strings?.dob_verify_button || "Verify");

    group.appendChild(input);
    group.appendChild(submitBtn);
    form.appendChild(instr);
    form.appendChild(group);
    body.appendChild(form);

    return { input, submitBtn, fmt };
  }

  // Normalize to YYYY-MM-DD given value and format (mdy|dmy).
  function normalizeDob(val, fmt) {
    if (!val) return null;
    val = val.trim();
    let digits = val.replace(/[^\d]/g, "");
    if (digits.length !== 8) return null;

    let m, d, y;
    if (fmt === "dmy") {
      d = digits.slice(0,2);
      m = digits.slice(2,4);
      y = digits.slice(4,8);
    } else {
      m = digits.slice(0,2);
      d = digits.slice(2,4);
      y = digits.slice(4,8);
    }

    const Yi = +y, Mi = +m, Di = +d;
    const dt = new Date(Date.UTC(Yi, Mi-1, Di));
    if (dt.getUTCFullYear() !== Yi || (dt.getUTCMonth()+1) !== Mi || dt.getUTCDate() !== Di) return null;

    return `${y}-${m}-${d}`;
  }

  // ---------- main behavior ----------
  Drupal.behaviors.simpleavsAgeGate = {
    attach(context) {
      const cfg = drupalSettings.simpleavs || {};
      if (!cfg.enabled) return;

      // Respect frequency BEFORE doing anything.
      if (!shouldPrompt(cfg)) return;

      const hosts = once("simpleavs-agegate", "body", context);
      if (!hosts.length) return;

      const { overlay, body, msg } = buildBase(cfg);
      document.body.appendChild(overlay);

      let token = null;
      let ready = false;

      function setBusy(b) {
        overlay.querySelectorAll("button").forEach(btn => btn.disabled = b);
      }
      setBusy(true);

      getJSON(cfg.endpoints.token).then(j => {
        token = j.token || null;
        ready = !!token;
        if (!ready) throw new Error("No token");
        setBusy(false);
      }).catch(() => {
        msg.textContent = "Could not obtain verification token.";
      });

      async function verify(data) {
        if (!ready || !token) {
          msg.textContent = "Invalid token.";
          return;
        }
        setBusy(true);
        try {
          const res = await postForm(cfg.endpoints.verify, { token, ...data });
          if (res && res.ok) {
            if (res.result === "passed") {
              // Record the pass per configured frequency.
              markPassed(cfg.frequency);
              if (cfg.redirects?.success) { window.location.href = cfg.redirects.success; return; }
              overlay.remove();
              return;
            }
            if (res.result === "denied") {
              if (cfg.redirects?.failure) { window.location.href = cfg.redirects.failure; return; }
              overlay.remove();
              return;
            }
            // Unknown but ok:true
            overlay.remove();
          } else {
            msg.textContent = (res && res.error) ? res.error : "Verification failed.";
            token = null; ready = false;
            try { const j = await getJSON(cfg.endpoints.token); token = j.token || null; ready = !!token; }
            catch {}
            setBusy(false);
          }
        } catch (e) {
          msg.textContent = "Verification failed.";
          token = null; ready = false;
          try { const j = await getJSON(cfg.endpoints.token); token = j.token || null; ready = !!token; }
          catch {}
          setBusy(false);
        }
      }

      if ((cfg.method || "question") === "dob") {
        const { input, submitBtn, fmt } = buildDobUI(cfg, body);
        submitBtn.addEventListener("click", () => {
          const norm = normalizeDob(input.value, fmt);
          if (!norm) {
            msg.textContent = cfg.strings?.dob_invalid_message || "Please enter a valid date of birth.";
            return;
          }
          verify({ action: "dob", dob: norm });
        });
      } else {
        const { yesBtn, noBtn } = buildQuestionUI(cfg, body);
        yesBtn.addEventListener("click", () => verify({ action: "yes" }));
        noBtn.addEventListener("click",  () => verify({ action: "no"  }));
      }
    }
  };
})(Drupal, drupalSettings);
