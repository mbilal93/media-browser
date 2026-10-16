'use strict';
const picker = new MediaBrowser();

async function refreshSession() {
  try {
    const session = await picker.session();
    document.querySelector('#login-panel').hidden = session.authorized;
    document.querySelector('#demo-panel').hidden = !session.authorized;
    document.querySelector('#logout').hidden = session.localOnly;
    if (!session.authorized && !session.passwordRequired) document.querySelector('#login-description').textContent = 'Remote access requires MEDIA_BROWSER_PASSWORD on the server. The unconfigured demo only accepts localhost.';
  } catch (error) { document.querySelector('#login-panel').hidden = false; document.querySelector('#login-error').textContent = error.message; }
}
document.querySelector('#login-form').onsubmit = async event => {
  event.preventDefault();
  try { await picker.request('login', new FormData(event.target)); location.reload(); }
  catch (error) { document.querySelector('#login-error').textContent = error.message; }
};
document.querySelector('#logout').onclick = async () => { await picker.request('logout', new URLSearchParams()); location.reload(); };
document.querySelector('#manage-library').onclick = () => picker.open({manage: true, multiple: true});
// The example form submits normally; PHP validates and restores the selected fields.
refreshSession();
