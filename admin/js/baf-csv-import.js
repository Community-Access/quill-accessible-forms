/**
 * CSV Import Wizard JavaScript
 *
 * @package BAF
 * @since 1.1.0
 */

(function($) {
    'use strict';

    // State management
    var state = {
        token: null,
        analysis: null,
        columnConfig: {},
        currentStep: 1
    };

    /**
     * Initialize the wizard
     */
    function init() {
        bindEvents();
    }

    /**
     * Bind all event handlers
     */
    function bindEvents() {
        // File upload
        $('#csv-file-input').on('change', handleFileSelect);
        $('#csv-dropzone').on('dragover dragenter', handleDragOver);
        $('#csv-dropzone').on('dragleave dragend', handleDragLeave);
        $('#csv-dropzone').on('drop', handleDrop);
        $('.baf-change-file').on('click', resetFileInput);

        // Step navigation
        $('#baf-csv-upload-form').on('submit', handleAnalyze);
        $('#btn-back-upload').on('click', function() { goToStep(1); });
        $('#btn-preview').on('click', handlePreview);
        $('#btn-back-configure').on('click', function() { goToStep(2); });
        $('#btn-create-form').on('click', handleCreateForm);
        $('#btn-import-another').on('click', resetWizard);

        // Preview tabs
        $('.tab-button').on('click', handleTabClick);
        $('#btn-copy-bfl').on('click', copyBflToClipboard);

        // Modal
        $(document).on('click', '.baf-edit-field', openFieldModal);
        $('.baf-modal-close, #btn-modal-cancel').on('click', closeFieldModal);
        $('#btn-modal-save').on('click', saveFieldChanges);
        $('#modal-field-type').on('change', toggleChoicesSection);
        $('#btn-add-choice').on('click', addChoice);
    }

    /**
     * Handle file selection
     */
    function handleFileSelect(e) {
        var file = e.target.files[0];
        if (file) {
            showSelectedFile(file);
        }
    }

    /**
     * Handle drag over
     */
    function handleDragOver(e) {
        e.preventDefault();
        e.stopPropagation();
        $('#csv-dropzone').addClass('dragover');
    }

    /**
     * Handle drag leave
     */
    function handleDragLeave(e) {
        e.preventDefault();
        e.stopPropagation();
        $('#csv-dropzone').removeClass('dragover');
    }

    /**
     * Handle file drop
     */
    function handleDrop(e) {
        e.preventDefault();
        e.stopPropagation();
        $('#csv-dropzone').removeClass('dragover');

        var files = e.originalEvent.dataTransfer.files;
        if (files.length > 0) {
            $('#csv-file-input')[0].files = files;
            showSelectedFile(files[0]);
        }
    }

    /**
     * Show selected file info
     */
    function showSelectedFile(file) {
        $('.dropzone-content').hide();
        $('.dropzone-selected').show();
        $('.selected-filename').text(file.name + ' (' + formatFileSize(file.size) + ')');
        $('#btn-analyze').prop('disabled', false);
    }

    /**
     * Reset file input
     */
    function resetFileInput() {
        $('#csv-file-input').val('');
        $('.dropzone-content').show();
        $('.dropzone-selected').hide();
        $('#btn-analyze').prop('disabled', true);
    }

    /**
     * Format file size
     */
    function formatFileSize(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    }

    /**
     * Handle analyze button
     */
    function handleAnalyze(e) {
        e.preventDefault();

        var $btn = $('#btn-analyze');
        var $spinner = $btn.find('.spinner');
        
        $btn.prop('disabled', true);
        $spinner.addClass('is-active');

        // Upload file first
        var formData = new FormData();
        formData.append('action', 'baf_csv_upload');
        formData.append('nonce', bafCsvImport.nonce);
        formData.append('csv_file', $('#csv-file-input')[0].files[0]);

        $.ajax({
            url: bafCsvImport.ajaxUrl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    state.token = response.data.token;
                    analyzeFile();
                } else {
                    showError(response.data.message || bafCsvImport.i18n.uploadError);
                    $btn.prop('disabled', false);
                    $spinner.removeClass('is-active');
                }
            },
            error: function() {
                showError(bafCsvImport.i18n.uploadError);
                $btn.prop('disabled', false);
                $spinner.removeClass('is-active');
            }
        });
    }

    /**
     * Analyze the uploaded file
     */
    function analyzeFile() {
        var delimiter = $('#csv-delimiter').val();
        if (delimiter === 'tab') delimiter = '\t';

        $.ajax({
            url: bafCsvImport.ajaxUrl,
            type: 'POST',
            data: {
                action: 'baf_csv_analyze',
                nonce: bafCsvImport.nonce,
                token: state.token,
                delimiter: delimiter,
                has_header: $('#csv-has-header').is(':checked'),
                infer_types: $('#csv-infer-types').is(':checked'),
                detect_choices: $('#csv-detect-choices').is(':checked'),
                detect_cascades: $('#csv-detect-cascades').is(':checked')
            },
            success: function(response) {
                $('#btn-analyze').prop('disabled', false).find('.spinner').removeClass('is-active');

                if (response.success) {
                    state.analysis = response.data.analysis;
                    state.columnConfig = {};
                    renderAnalysisResults(response.data);
                    goToStep(2);
                } else {
                    showError(response.data.message || bafCsvImport.i18n.analyzeError);
                }
            },
            error: function() {
                $('#btn-analyze').prop('disabled', false).find('.spinner').removeClass('is-active');
                showError(bafCsvImport.i18n.analyzeError);
            }
        });
    }

    /**
     * Render analysis results
     */
    function renderAnalysisResults(data) {
        var analysis = data.analysis;
        var report = data.report;

        // Summary
        var summaryHtml = '<p><strong>' + analysis.total_rows + '</strong> rows found, ';
        summaryHtml += '<strong>' + analysis.columns.length + '</strong> columns detected.</p>';
        
        if (report.summary.choice_fields > 0) {
            summaryHtml += '<p>' + report.summary.choice_fields + ' dropdown fields detected.</p>';
        }
        if (report.summary.cascade_pairs > 0) {
            summaryHtml += '<p>' + report.summary.cascade_pairs + ' cascade relationship(s) found.</p>';
        }

        $('#analysis-summary').html(summaryHtml);

        // Set default form title
        if (!$('#form-title').val()) {
            $('#form-title').val('Form from ' + analysis.file);
        }

        // Field mapping table
        var tbody = $('#field-mapping-body');
        tbody.empty();

        analysis.columns.forEach(function(col, index) {
            var confidenceClass = col.confidence >= 0.8 ? 'high' : (col.confidence >= 0.5 ? 'medium' : 'low');
            var confidenceLabel = col.confidence >= 0.8 ? bafCsvImport.i18n.highConfidence : 
                                  (col.confidence >= 0.5 ? bafCsvImport.i18n.medConfidence : bafCsvImport.i18n.lowConfidence);

            var row = '<tr data-index="' + index + '">';
            row += '<td><input type="checkbox" class="column-include" checked aria-label="Include column"></td>';
            row += '<td class="column-header">' + escapeHtml(col.header) + '</td>';
            row += '<td class="column-type"><span class="field-type-badge">' + col.suggested_type + '</span>';
            if (col.choices && col.choices.length > 0) {
                row += ' <span class="choice-count">(' + col.choices.length + ' options)</span>';
            }
            row += '</td>';
            row += '<td class="column-confidence"><span class="confidence-' + confidenceClass + '">' + confidenceLabel + '</span></td>';
            row += '<td class="column-label"><input type="text" class="field-label" value="' + escapeHtml(col.field_label) + '"></td>';
            row += '<td class="column-required"><input type="checkbox" class="field-required"' + (col.is_required ? ' checked' : '') + '></td>';
            row += '<td class="column-actions"><button type="button" class="button button-small baf-edit-field" data-index="' + index + '">Edit</button></td>';
            row += '</tr>';

            tbody.append(row);
        });

        // Cascade info
        if (analysis.cascades && analysis.cascades.length > 0) {
            var cascadeHtml = '<ul>';
            analysis.cascades.forEach(function(cascade) {
                cascadeHtml += '<li><strong>' + escapeHtml(cascade.child_column) + '</strong>';
                cascadeHtml += ' will filter based on <strong>' + escapeHtml(cascade.parent_column) + '</strong>';
                cascadeHtml += ' <span class="confidence-medium">(' + Math.round(cascade.confidence * 100) + '% confidence)</span></li>';
            });
            cascadeHtml += '</ul>';
            $('#cascade-list').html(cascadeHtml);
            $('#cascade-info').show();
        } else {
            $('#cascade-info').hide();
        }
    }

    /**
     * Handle preview button
     */
    function handlePreview() {
        var $btn = $('#btn-preview');
        var $spinner = $btn.find('.spinner');

        $btn.prop('disabled', true);
        $spinner.addClass('is-active');

        // Gather column config from table
        gatherColumnConfig();

        // Get excluded columns
        var excludeColumns = [];
        $('#field-mapping-body tr').each(function() {
            if (!$(this).find('.column-include').is(':checked')) {
                excludeColumns.push(parseInt($(this).data('index'), 10));
            }
        });

        $.ajax({
            url: bafCsvImport.ajaxUrl,
            type: 'POST',
            data: {
                action: 'baf_csv_preview',
                nonce: bafCsvImport.nonce,
                token: state.token,
                form_title: $('#form-title').val(),
                form_description: $('#form-description').val(),
                column_config: JSON.stringify(state.columnConfig),
                exclude_columns: excludeColumns
            },
            success: function(response) {
                $btn.prop('disabled', false);
                $spinner.removeClass('is-active');

                if (response.success) {
                    renderFormPreview(response.data);
                    goToStep(3);
                } else {
                    showError(response.data.message);
                }
            },
            error: function() {
                $btn.prop('disabled', false);
                $spinner.removeClass('is-active');
                showError('Preview failed.');
            }
        });
    }

    /**
     * Gather column configuration from table inputs
     */
    function gatherColumnConfig() {
        $('#field-mapping-body tr').each(function() {
            var index = parseInt($(this).data('index'), 10);
            var config = state.columnConfig[index] || {};
            
            config.label = $(this).find('.field-label').val();
            config.required = $(this).find('.field-required').is(':checked');

            state.columnConfig[index] = config;
        });
    }

    /**
     * Render form preview
     */
    function renderFormPreview(data) {
        var form = data.form;
        var bfl = data.bfl;

        // Visual preview
        var previewHtml = '<div class="baf-preview-form">';
        previewHtml += '<h3>' + escapeHtml(form.title) + '</h3>';
        
        if (form.description) {
            previewHtml += '<p class="form-description">' + escapeHtml(form.description) + '</p>';
        }

        form.fields.forEach(function(field) {
            previewHtml += renderFieldPreview(field);
        });

        previewHtml += '</div>';
        $('#form-preview').html(previewHtml);

        // BFL code
        $('#bfl-output').val(bfl);
    }

    /**
     * Render a single field preview
     */
    function renderFieldPreview(field) {
        var html = '<div class="preview-field">';
        
        if (field.type === 'submit') {
            html += '<button type="button" class="button button-primary">' + escapeHtml(field.label) + '</button>';
        } else {
            html += '<label>';
            html += escapeHtml(field.label);
            if (field.required) {
                html += ' <span class="required">*</span>';
            }
            html += '</label>';

            switch (field.type) {
                case 'textarea':
                    html += '<textarea rows="' + (field.rows || 4) + '" disabled></textarea>';
                    break;
                case 'select':
                    html += '<select disabled>';
                    html += '<option>-- Select --</option>';
                    if (field.choices) {
                        field.choices.forEach(function(choice) {
                            html += '<option>' + escapeHtml(choice.label) + '</option>';
                        });
                    }
                    html += '</select>';
                    break;
                case 'radio':
                    if (field.choices) {
                        field.choices.forEach(function(choice) {
                            html += '<label class="radio-label"><input type="radio" disabled> ' + escapeHtml(choice.label) + '</label>';
                        });
                    }
                    break;
                case 'checkbox':
                    if (field.choices && field.choices.length > 1) {
                        field.choices.forEach(function(choice) {
                            html += '<label class="checkbox-label"><input type="checkbox" disabled> ' + escapeHtml(choice.label) + '</label>';
                        });
                    } else {
                        html += '<input type="checkbox" disabled>';
                    }
                    break;
                default:
                    html += '<input type="' + (field.type || 'text') + '" disabled>';
            }
        }

        html += '</div>';
        return html;
    }

    /**
     * Handle form creation
     */
    function handleCreateForm() {
        var $btn = $('#btn-create-form');
        var $spinner = $btn.find('.spinner');

        $btn.prop('disabled', true);
        $spinner.addClass('is-active');

        gatherColumnConfig();

        var excludeColumns = [];
        $('#field-mapping-body tr').each(function() {
            if (!$(this).find('.column-include').is(':checked')) {
                excludeColumns.push(parseInt($(this).data('index'), 10));
            }
        });

        $.ajax({
            url: bafCsvImport.ajaxUrl,
            type: 'POST',
            data: {
                action: 'baf_csv_create_form',
                nonce: bafCsvImport.nonce,
                token: state.token,
                form_title: $('#form-title').val(),
                form_description: $('#form-description').val(),
                column_config: JSON.stringify(state.columnConfig),
                exclude_columns: excludeColumns
            },
            success: function(response) {
                $btn.prop('disabled', false);
                $spinner.removeClass('is-active');

                if (response.success) {
                    $('#success-message').text(response.data.message);
                    $('#edit-form-link').attr('href', response.data.edit_url);
                    goToStep(4);
                } else {
                    showError(response.data.message || bafCsvImport.i18n.createError);
                }
            },
            error: function() {
                $btn.prop('disabled', false);
                $spinner.removeClass('is-active');
                showError(bafCsvImport.i18n.createError);
            }
        });
    }

    /**
     * Navigate to a step
     */
    function goToStep(step) {
        state.currentStep = step;

        // Update step indicators
        $('.baf-step').removeClass('active completed');
        $('.baf-step').each(function() {
            var stepNum = parseInt($(this).data('step'), 10);
            if (stepNum < step) {
                $(this).addClass('completed');
            } else if (stepNum === step) {
                $(this).addClass('active');
            }
        });

        // Show/hide step content
        $('.baf-wizard-step').hide();
        
        switch (step) {
            case 1:
                $('#step-upload').show();
                break;
            case 2:
                $('#step-configure').show();
                break;
            case 3:
                $('#step-preview').show();
                break;
            case 4:
                $('#step-success').show();
                break;
        }
    }

    /**
     * Reset wizard to start
     */
    function resetWizard() {
        state.token = null;
        state.analysis = null;
        state.columnConfig = {};
        resetFileInput();
        $('#form-title').val('');
        $('#form-description').val('');
        goToStep(1);
    }

    /**
     * Handle tab click
     */
    function handleTabClick() {
        var tab = $(this).data('tab');
        
        $('.tab-button').removeClass('active').attr('aria-selected', 'false');
        $(this).addClass('active').attr('aria-selected', 'true');
        
        $('.tab-panel').hide().removeClass('active');
        $('#preview-' + tab).show().addClass('active');
    }

    /**
     * Copy BFL to clipboard
     */
    function copyBflToClipboard() {
        var bfl = $('#bfl-output').val();
        navigator.clipboard.writeText(bfl).then(function() {
            var $btn = $('#btn-copy-bfl');
            var originalText = $btn.text();
            $btn.text(bafCsvImport.i18n.copied);
            setTimeout(function() {
                $btn.text(originalText);
            }, 2000);
        });
    }

    /**
     * Open field edit modal
     */
    function openFieldModal() {
        var index = parseInt($(this).data('index'), 10);
        var col = state.analysis.columns[index];
        var config = state.columnConfig[index] || {};

        $('#modal-column-index').val(index);
        $('#modal-field-type').val(config.type || col.suggested_type);
        $('#modal-field-id').val(config.field_id || col.field_id);
        $('#modal-field-label').val(config.label || col.field_label);
        $('#modal-field-required').prop('checked', config.required !== undefined ? config.required : col.is_required);

        // Handle choices
        var choices = config.choices || col.choices || [];
        renderModalChoices(choices);
        toggleChoicesSection();

        $('#field-edit-modal').show();
    }

    /**
     * Close field edit modal
     */
    function closeFieldModal() {
        $('#field-edit-modal').hide();
    }

    /**
     * Toggle choices section based on field type
     */
    function toggleChoicesSection() {
        var type = $('#modal-field-type').val();
        var choiceTypes = ['select', 'radio', 'checkbox'];
        
        if (choiceTypes.indexOf(type) !== -1) {
            $('#modal-choices-section').show();
        } else {
            $('#modal-choices-section').hide();
        }
    }

    /**
     * Render choices in modal
     */
    function renderModalChoices(choices) {
        var container = $('#modal-choices-list');
        container.empty();

        choices.forEach(function(choice, index) {
            var html = '<div class="choice-row" data-index="' + index + '">';
            html += '<input type="text" class="choice-value" placeholder="Value" value="' + escapeHtml(choice.value) + '">';
            html += '<input type="text" class="choice-label" placeholder="Label" value="' + escapeHtml(choice.label) + '">';
            html += '<button type="button" class="button-link remove-choice" aria-label="Remove">&times;</button>';
            html += '</div>';
            container.append(html);
        });

        // Bind remove handlers
        container.find('.remove-choice').on('click', function() {
            $(this).closest('.choice-row').remove();
        });
    }

    /**
     * Add a new choice
     */
    function addChoice() {
        var container = $('#modal-choices-list');
        var index = container.find('.choice-row').length;

        var html = '<div class="choice-row" data-index="' + index + '">';
        html += '<input type="text" class="choice-value" placeholder="Value">';
        html += '<input type="text" class="choice-label" placeholder="Label">';
        html += '<button type="button" class="button-link remove-choice" aria-label="Remove">&times;</button>';
        html += '</div>';
        
        container.append(html);
        container.find('.choice-row:last .choice-value').focus();

        // Bind remove handler
        container.find('.choice-row:last .remove-choice').on('click', function() {
            $(this).closest('.choice-row').remove();
        });
    }

    /**
     * Save field changes from modal
     */
    function saveFieldChanges() {
        var index = parseInt($('#modal-column-index').val(), 10);

        // Gather choices
        var choices = [];
        $('#modal-choices-list .choice-row').each(function() {
            var value = $(this).find('.choice-value').val().trim();
            var label = $(this).find('.choice-label').val().trim();
            if (value || label) {
                choices.push({
                    value: value || label.toLowerCase().replace(/\s+/g, '_'),
                    label: label || value
                });
            }
        });

        state.columnConfig[index] = {
            type: $('#modal-field-type').val(),
            field_id: $('#modal-field-id').val(),
            label: $('#modal-field-label').val(),
            required: $('#modal-field-required').is(':checked'),
            choices: choices.length > 0 ? choices : undefined
        };

        // Update table row
        var $row = $('#field-mapping-body tr[data-index="' + index + '"]');
        $row.find('.field-label').val(state.columnConfig[index].label);
        $row.find('.field-required').prop('checked', state.columnConfig[index].required);
        
        var typeHtml = '<span class="field-type-badge">' + state.columnConfig[index].type + '</span>';
        if (choices.length > 0) {
            typeHtml += ' <span class="choice-count">(' + choices.length + ' options)</span>';
        }
        $row.find('.column-type').html(typeHtml);

        closeFieldModal();
    }

    /**
     * Show error message
     */
    function showError(message) {
        // Use WordPress admin notices if available
        var notice = $('<div class="notice notice-error is-dismissible"><p>' + escapeHtml(message) + '</p></div>');
        $('.baf-csv-import-wizard h1').after(notice);
        
        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            notice.fadeOut(function() { $(this).remove(); });
        }, 5000);
    }

    /**
     * Escape HTML
     */
    function escapeHtml(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Initialize when ready
    $(document).ready(init);

})(jQuery);
