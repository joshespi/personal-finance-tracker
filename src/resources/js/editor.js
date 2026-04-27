import EasyMDE from 'easymde';
import 'easymde/dist/easymde.min.css';

document.querySelectorAll('textarea.markdown-editor').forEach((el) => {
    new EasyMDE({
        element: el,
        spellChecker: false,
        status: false,
        autosave: { enabled: false },
        toolbar: [
            'bold', 'italic', 'heading', '|',
            'quote', 'unordered-list', 'ordered-list', '|',
            'link', 'code', '|',
            'preview', 'guide',
        ],
        minHeight: '140px',
    });
});
