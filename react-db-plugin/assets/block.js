(function(blocks, element, components){
    var el = element.createElement;
    var TextControl = components.TextControl;

    blocks.registerBlockType('reactdb/block', {
        title: 'React DB Block',
        icon: 'database',
        category: 'widgets',
        attributes: {
            input: { type: 'string', default: '' }
        },
        edit: function(props){
            return el('div', { className: props.className },
                el(TextControl, {
                    label: 'Input',
                    value: props.attributes.input,
                    onChange: function(val){ props.setAttributes({input: val}); }
                })
            );
        },
        save: function(){
            return null; // server-side
        }
    });
})(window.wp.blocks, window.wp.element, window.wp.components);
