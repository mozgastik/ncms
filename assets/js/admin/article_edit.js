// Ініціалізація CKEditor
let editor = null;

document.addEventListener('DOMContentLoaded', function() {
    // Ініціалізація CKEditor
    if (CKEDITOR && document.querySelector('#article-content')) {
        editor = CKEDITOR.replace('article-content', {
            toolbar: [
                { name: 'document', items: ['Source', '-', 'Save', 'NewPage', 'Preview', 'Print', '-', 'Templates'] },
                { name: 'clipboard', items: ['Cut', 'Copy', 'Paste', 'PasteText', 'PasteFromWord', '-', 'Undo', 'Redo'] },
                { name: 'editing', items: ['Find', 'Replace', '-', 'SelectAll', '-', 'Scayt'] },
                { name: 'basicstyles', items: ['Bold', 'Italic', 'Underline', 'Strike', 'Subscript', 'Superscript', '-', 'CopyFormatting', 'RemoveFormat'] },
                { name: 'paragraph', items: ['NumberedList', 'BulletedList', '-', 'Outdent', 'Indent', '-', 'Blockquote', '-', 'JustifyLeft', 'JustifyCenter', 'JustifyRight', 'JustifyBlock'] },
                { name: 'links', items: ['Link', 'Unlink'] },
                { name: 'insert', items: ['Image', 'Table', 'HorizontalRule', 'SpecialChar'] },
                '/',
                { name: 'styles', items: ['Styles', 'Format', 'Font', 'FontSize'] },
                { name: 'colors', items: ['TextColor', 'BGColor'] },
                { name: 'tools', items: ['Maximize', 'ShowBlocks'] }
            ],
            language: 'uk',
            height: 400,
            extraPlugins: 'uploadimage',
            uploadUrl: '{{ path("admin_article_upload_image", {"id": article.id}) }}'
        });

        editor.on('change', updateContentStats);
    }

    // Ініціалізація основних функцій
    initSlugGeneration();
    initFileUpload();
    initAutoSave();
});

// Функції для глобального використання
window.insertImageToEditor = insertImageToEditor;
window.copyImageUrl = copyImageUrl;
window.deleteImage = deleteImage;
window.insertTemplate = insertTemplate;
window.toggleFullscreen = toggleFullscreen;
window.saveAsDraft = saveAsDraft;
window.quickPreview = quickPreview;
window.duplicateArticle = duplicateArticle;
window.updatePublishStatus = updatePublishStatus;