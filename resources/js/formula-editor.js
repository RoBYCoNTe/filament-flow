(function () {
    if (window.__ffAlpineRegistered) return;
    window.__ffAlpineRegistered = true;

    document.addEventListener('alpine:init', function () {
        Alpine.data('formulaEditor', function ({ state, completionsUrl, height }) {
            return {
                state: state,
                editor: null,
                _themeCompartment: null,
                _themeObserver: null,
                _completionsCache: null,
                showVars: false,
                vars: [],

                // Shared fetch — used by both autocomplete and the vars tooltip.
                async _fetchCompletions() {
                    if (this._completionsCache !== null) return this._completionsCache;
                    if (!completionsUrl) return (this._completionsCache = { variables: [], stringValues: {} });
                    try {
                        const r = await fetch(completionsUrl);
                        this._completionsCache = r.ok ? await r.json() : { variables: [], stringValues: {} };
                    } catch (_) {
                        this._completionsCache = { variables: [], stringValues: {} };
                    }
                    return this._completionsCache;
                },

                async toggleVars() {
                    this.showVars = !this.showVars;
                    if (this.showVars && !this.vars.length) {
                        const payload = await this._fetchCompletions();
                        this.vars = payload.variables ?? [];
                    }
                },

                async init() {
                    const self = this;

                    const [
                        { EditorView, keymap, lineNumbers, drawSelection, highlightActiveLine },
                        { EditorState, Compartment },
                        { defaultKeymap, history, historyKeymap, indentWithTab },
                        { autocompletion, completionKeymap },
                    ] = await Promise.all([
                        import('https://esm.sh/@codemirror/view@6'),
                        import('https://esm.sh/@codemirror/state@6'),
                        import('https://esm.sh/@codemirror/commands@6'),
                        import('https://esm.sh/@codemirror/autocomplete@6'),
                    ]);

                    if (!this.$refs.editor) return;

                    const themeCompartment = new Compartment();
                    this._themeCompartment = themeCompartment;

                    const isDark = () =>
                        document.documentElement.classList.contains('dark');

                    // Insert `name()` with the cursor between the parentheses.
                    const callApply = (name) => (view, completion, from, to) => {
                        view.dispatch({
                            changes: { from, to, insert: `${name}()` },
                            selection: { anchor: from + name.length + 1 },
                        });
                    };

                    // Same for method suggestions (e.g. `.sum('field')`).
                    const methodApply = (name) => (view, completion, from, to) => {
                        view.dispatch({
                            changes: { from, to, insert: `${name}()` },
                            selection: { anchor: from + name.length + 1 },
                        });
                    };

                    const completionSource = async (ctx) => {
                        const payload = await self._fetchCompletions();

                        if (ctx.matchBefore(/['"][^'"]*$/)) {
                            const opts = [];
                            for (const list of Object.values(payload.stringValues ?? {})) {
                                for (const s of list) opts.push({ label: s, type: 'text' });
                            }
                            return opts.length ? { from: ctx.pos, options: opts, validFor: /^\w*$/ } : null;
                        }

                        // Member completion after a variable OR a call result:
                        // `scheme.`, `siblings().`, `children('slug').`, `field('x').`
                        const afterDot = ctx.matchBefore(/(\w+)(?:\([^()]*\))?\.\w*$/);
                        if (afterDot) {
                            const varName = (afterDot.text.match(/^(\w+)/) ?? [null, ''])[1];
                            const v = (payload.variables ?? []).find(x => x.name === varName);
                            if (!v) return null;
                            // position right after the last dot
                            const dotPos = afterDot.from + afterDot.text.lastIndexOf('.') + 1;
                            const opts = [
                                ...(v.properties ?? []).map(p => ({ label: p.name, type: 'property', info: p.type ?? '' })),
                                ...(v.methods ?? []).map(m => ({
                                    label: m.name + '()',
                                    apply: methodApply(m.name),
                                    type: 'method',
                                    info: m.signature ?? '',
                                })),
                            ];
                            return opts.length ? { from: dotPos, options: opts, validFor: /^\w*$/ } : null;
                        }

                        const word = ctx.matchBefore(/\w*/);
                        if (!word || (word.from === word.to && !ctx.explicit)) return null;
                        const opts = (payload.variables ?? []).map(v => {
                            const isFunction = (v.kind === 'function');

                            return {
                                // functions are inserted as calls, cursor inside the parens
                                label: isFunction ? `${v.name}()` : v.name,
                                apply: isFunction ? callApply(v.name) : v.name,
                                type: v.kind ?? 'variable',
                                info: v.description ?? '',
                            };
                        });
                        return { from: word.from, options: opts, validFor: /^\w*$/ };
                    };

                    this.editor = new EditorView({
                        parent: this.$refs.editor,
                        state: EditorState.create({
                            doc: this.state ?? '',
                            extensions: [
                                lineNumbers(),
                                drawSelection(),
                                highlightActiveLine(),
                                history(),
                                keymap.of([
                                    indentWithTab,
                                    ...defaultKeymap,
                                    ...historyKeymap,
                                    ...completionKeymap,
                                ]),
                                autocompletion({ override: [completionSource] }),
                                EditorView.lineWrapping,
                                themeCompartment.of([]),
                                EditorView.theme({
                                    // Fixed height with internal scroll: the editor
                                    // never collapses nor stretches its container.
                                    '&': { height: '100%' },
                                    '.cm-scroller': { overflow: 'auto' },
                                }),
                                EditorView.updateListener.of((update) => {
                                    if (!update.docChanged) return;
                                    self.state = update.state.doc.toString();
                                }),
                            ],
                        }),
                    });

                    // Apply the configured height (e.g. '100px'): without it the
                    // editor container has no intrinsic height.
                    if (height) {
                        this.$refs.editor.style.height = height;
                    }

                    this.$watch('state', (val) => {
                        if (!this.editor) return;
                        const current = this.editor.state.doc.toString();
                        if (current === (val ?? '')) return;
                        this.editor.dispatch({
                            changes: { from: 0, to: current.length, insert: val ?? '' },
                        });
                    });

                    const applyTheme = async () => {
                        if (!this.editor) return;
                        let ext = [];
                        if (isDark()) {
                            const { oneDark } = await import('https://esm.sh/@codemirror/theme-one-dark@6');
                            ext = oneDark;
                        }
                        this.editor.dispatch({ effects: themeCompartment.reconfigure(ext) });
                    };

                    if (isDark()) applyTheme();

                    this._themeObserver = new MutationObserver(applyTheme);
                    this._themeObserver.observe(document.documentElement, {
                        attributes: true,
                        attributeFilter: ['class'],
                    });
                },

                destroy() {
                    if (this._themeObserver) this._themeObserver.disconnect();
                    if (this.editor) this.editor.destroy();
                },
            };
        });
    });
})();
