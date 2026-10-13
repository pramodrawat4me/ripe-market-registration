const API_URL = 'api.php';
const LOGO = 'https://www.twobrothersindiashop.ae/cdn/shop/files/LOGO_TWO_BROTHERS_INDIA_FARMS_MIDDLE_EAST_AND_NORTH_AFRICA.png?height=80&v=1756364201';
const $ = s => document.querySelector(s);
const esc = s => String(s ?? '').replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
const logo = () => `<div class="brand"><img src="${LOGO}" alt="Two Brothers" width="124" height="80"></div>`;
async function api(action, data = {}, pinKey) {
  const pin = pinKey ? (sessionStorage.getItem(pinKey) || '') : '';
  try {
    const r = await fetch(API_URL, { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ action, pin, ...data }) });
    const j = await r.json(); j.status = r.status; return j;
  } catch (e) { return { ok:false, error:'Network error. Please try again.' }; }
}


