const globals = window.ReactDbGlobals || {};
export const currentUser = globals.currentUser || '';
export const logoutUrl = globals.logoutUrl || '';
export let apiNonce = globals.nonce || '';
export const setApiNonce = nonce => { apiNonce = nonce; };
export const authConfig = { page: globals.auth, ajaxUrl: globals.ajaxUrl, canonicalAppUrl: globals.canonicalAppUrl };

const rawApiBase = globals.apiBase || '/wp-json/reactdb/v1/';
const normalizedApiBase = rawApiBase.endsWith('/') ? rawApiBase : `${rawApiBase}/`;

export const apiBase = normalizedApiBase;
export const apiEndpoint = (path = '') => normalizedApiBase + path.replace(/^\//, '');

const isPlugin = Boolean(globals.isPlugin);
export default isPlugin;
