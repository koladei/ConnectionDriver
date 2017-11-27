(function ($) {
  $(function () {
    var inputs = $('.logic-editor');
    inputs.each(function(){
      var input = $(this);
      input.hide();
      var editorDiv = $('<div/>').insertAfter(input);
      editorDiv.uniqueId().attr('class', input.attr('class')).css({
        minHeight: '100px'
      });
  
      var editor = ace.edit(editorDiv.attr('id'));
      var doc = editor.getSession().getDocument(); 
      editor.getSession().setMode("ace/mode/javascript");
      editor.setTheme("ace/theme/twilight");
      editor.setOptions({
        maxLines: Infinity
      });
      editor.getSession().setTabSize(2);
      editor.getSession().setUseWrapMode(true);
      editor.getSession().setValue(input.val());
      editor.getSession().on('change', function () {
        input.val(editor.getSession().getValue());
        var lineHeight = editor.renderer.lineHeight;
        editorDiv.css({
          height: lineHeight * doc.getLength() + "px"
        });
  
        editor.resize();
      });
    });    
  })
})(jQuery);
