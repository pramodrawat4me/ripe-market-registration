const API_URL = '/api/api.php';

const LOGO = 'https://www.twobrothersindiashop.ae/cdn/shop/files/LOGO_TWO_BROTHERS_INDIA_FARMS_MIDDLE_EAST_AND_NORTH_AFRICA.png?height=80&v=1756364201';

const $ = (selector) => document.querySelector(selector);

const esc = (value) =>
  String(value ?? '').replace(/[&<>"]/g, (char) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;'
  }[char]));

const logo = () => `
  <div class="brand">
    <img
      src="${LOGO}"
      alt="Two Brothers"
      width="124"
      height="80"
    >
  </div>
`;

async function api(action, data = {}, pinKey) {
  const pin = pinKey
    ? (sessionStorage.getItem(pinKey) || '')
    : '';

  try {
    const response = await fetch(API_URL, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify({
        ...data,
        action,
        pin
      })
    });

    const responseText = await response.text();

    let result;

    try {
      result = JSON.parse(responseText);
    } catch {
      console.error(
        'API returned a non-JSON response.',
        'Status:',
        response.status,
        'Response:',
        responseText.slice(0, 500)
      );

      return {
        ok: false,
        status: response.status,
        error: response.status === 404
          ? 'API endpoint not found. Check the Vercel API deployment.'
          : 'The server returned an invalid response. Check Vercel logs.'
      };
    }

    result.status = response.status;

    if (!response.ok && result.ok !== false) {
      result.ok = false;
      result.error = 'Server request failed (' + response.status + ').';
    }

    return result;

  } catch (error) {
    console.error('API network error:', error);

    return {
      ok: false,
      status: 0,
      error: 'Unable to connect to the server. Check your network and API deployment.'
    };
  }
}