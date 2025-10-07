const globals = window.ReactDbGlobals || {};
export const currentUser = globals.currentUser || '';
export const logoutUrl = globals.logoutUrl || '';
export const apiNonce = globals.nonce || '';

const rawApiBase = globals.apiBase || '/wp-json/reactdb/v1/';
const normalizedApiBase = rawApiBase.endsWith('/') ? rawApiBase : `${rawApiBase}/`;

export const apiBase = normalizedApiBase;
export const apiEndpoint = (path = '') => normalizedApiBase + path.replace(/^\//, '');

const isPlugin = Boolean(globals.isPlugin);
export default isPlugin;
