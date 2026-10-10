/**
 * FAQ list editor: add, remove and reorder questions, then save the whole list in one request.
 * The order of the question blocks on the page is the order that is saved.
 */
(function () {
    'use strict';

    const config = window.FaqConfig || {};
    const form = document.getElementById('faqForm');
    if (!form) return;

    const container = document.getElementById('faqItems');
    const emptyNote = document.getElementById('faqEmpty');
    const template = document.getElementById('faqItemTemplate');
    const result = document.getElementById('faqResult');
    const saveButton = document.getElementById('faqSaveButton');
    const nameInput = document.getElementById('faqName');
    const slugInput = document.getElementById('faqSlug');
    const idInput = document.getElementById('faqId');

    function refreshEmptyNote() {
        emptyNote.classList.toggle('d-none', container.children.length > 0);
    }

    function showResult(message, type) {
        result.className = 'alert alert-' + type;
        result.textContent = message;
        result.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function readItems() {
        return Array.from(container.querySelectorAll('[data-faq-item]')).map(function (block) {
            return {
                id: Number(block.dataset.id) || 0,
                question: block.querySelector('[data-field="question"]').value,
                answer: block.querySelector('[data-field="answer"]').value,
                is_published: block.querySelector('[data-field="published"]').checked
            };
        });
    }

    document.getElementById('faqAddButton').addEventListener('click', function () {
        const block = template.content.firstElementChild.cloneNode(true);
        container.appendChild(block);
        refreshEmptyNote();
        block.querySelector('[data-field="question"]').focus();
    });

    container.addEventListener('click', function (event) {
        const block = event.target.closest('[data-faq-item]');
        if (!block) return;

        if (event.target.closest('[data-remove]')) {
            block.remove();
            refreshEmptyNote();
            return;
        }

        const move = event.target.closest('[data-move]');
        if (!move) return;
        if (move.dataset.move === 'up' && block.previousElementSibling) {
            container.insertBefore(block, block.previousElementSibling);
        }
        if (move.dataset.move === 'down' && block.nextElementSibling) {
            container.insertBefore(block.nextElementSibling, block);
        }
    });

    let slugEdited = slugInput.value !== '';
    slugInput.addEventListener('input', function () { slugEdited = true; });
    nameInput.addEventListener('input', function () {
        if (slugEdited) return;
        slugInput.value = nameInput.value.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
    });

    form.addEventListener('submit', async function (event) {
        event.preventDefault();
        saveButton.disabled = true;

        try {
            const response = await fetch(config.actionUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    csrf_token: window.csrfToken,
                    action: 'save',
                    id: Number(idInput.value) || 0,
                    name: nameInput.value,
                    slug: slugInput.value,
                    items: readItems()
                })
            });
            const data = await response.json();

            if (!data.success) {
                showResult(data.error || data.message || 'Could not save the list.', 'danger');
                saveButton.disabled = false;
                return;
            }

            // Go to the list by id rather than reloading: core strips the query string from this page's address
            // (it is stashed in the session and cleared on load), so a reload would open a blank "New List"
            window.location.href = config.editUrl + '?id=' + data.id;
        } catch (error) {
            console.error('Save error:', error);
            showResult('Could not save the list.', 'danger');
            saveButton.disabled = false;
        }
    });
})();
