const globals = window.ReactDbGlobals || {};
export const currentUser = globals.currentUser || '';
export const logoutUrl = globals.logoutUrl || '';
const isPlugin = Boolean(globals.isPlugin);
export default isPlugin;
