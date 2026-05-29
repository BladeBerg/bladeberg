/**
 * isolated-block-editor 2.30 reads webpack externals `React` / `ReactDOM` globals.
 * It requires React 18 internals — React 19 will crash at load time.
 */
export function assignReactGlobals(React, ReactDOM) {
    window.React = React;
    window.ReactDOM = ReactDOM;
    globalThis.React = React;
    globalThis.ReactDOM = ReactDOM;
}
