jQuery(document).ready(function ($) {
    var PPRAdmin = {
        selectedFile: null,
        previewData: null,

        init: function () {
            this.bindEvents();
        },

        bindEvents: function () {
            var self = this;

            // Drop zone events
            $(document).on('click', '#ppr-drop-zone', function (e) {
                if (e.target !== this) {
                    return;
                }
                $('#ppr-file-input').click();
            });

            // Drag and drop events
            $(document).on('dragover', '#ppr-drop-zone', function (e) {
                e.preventDefault();
                $(this).addClass('dragover');
            });

            $(document).on('dragleave', '#ppr-drop-zone', function (e) {
                e.preventDefault();
                $(this).removeClass('dragover');
            });

            $(document).on('drop', '#ppr-drop-zone', function (e) {
                e.preventDefault();
                $(this).removeClass('dragover');

                var files = e.originalEvent.dataTransfer.files;
                if (files.length > 0) {
                    self.handleFileSelect(files[0]);
                }
            });

            // File input change
            $(document).on('change', '#ppr-file-input', function () {
                var file = this.files[0];
                if (file) {
                    self.handleFileSelect(file);
                }
            });

            // radio button change
            $(document).on('change', 'input[name="ppr_upload_mode"]', function () {
                //enable upload button
                if (self.selectedFile) {
                    $('#ppr-upload-btn').prop('disabled', false);
                }
            });


            // Preview button
            $(document).on('click', '#ppr-preview-btn', function () {
                self.showPreview();
            });

            // Upload button
            $(document).on('click', '#ppr-upload-btn', function () {
                
            });

            // Modal close
            $(document).on('click', '.ppr-modal-close, .ppr-modal', function (e) {
                if (e.target === this) {
                    $('#ppr-preview-modal').hide();
                }
            });

            // Prevent modal close when clicking inside modal content
            $(document).on('click', '.ppr-modal-content', function (e) {
                e.stopPropagation();
            });
        },

        handleFileSelect: function (file) {
            if (!this.validateFile(file)) {
                return;
            }

            this.selectedFile = file;
            this.showFileInfo(file);
            this.previewFile();
        },

        validateFile: function (file) {
            var validTypes = ['text/csv', 'application/vnd.ms-excel', 'text/plain'];
            var validExtensions = ['csv'];
            var fileExtension = file.name.split('.').pop().toLowerCase();

            if (!validTypes.includes(file.type) && !validExtensions.includes(fileExtension)) {
                this.showMessage('error', 'Invalid file type. Please upload a CSV file.');
                return false;
            }

            return true;
        },

        showFileInfo: function (file) {
            var fileInfo = $('#ppr-file-info');
            fileInfo.html(
                '<strong>' + file.name + '</strong><br>' +
                'Size: ' + this.formatFileSize(file.size) + '<br>' +
                'Type: ' + file.type
            ).show();

            $('#ppr-options-wrapper').show();
        },

        formatFileSize: function (bytes) {
            if (bytes === 0) return '0 Bytes';
            var k = 1024;
            var sizes = ['Bytes', 'KB', 'MB', 'GB'];
            var i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        },

        previewFile: function () {
            var self = this;

            // Show loading
            $('#ppr-loading').show();
            $('.ppr-drop-content').hide();

            // use Papa Parse to parse the CSV file
            Papa.parse(this.selectedFile, {
                header: true,
                skipEmptyLines: true,
                complete: function (results) {
                    $('#ppr-loading').hide();
                    $('.ppr-drop-content').show();

                    if (results.errors.length > 0) {
                        self.showMessage('error', 'Error parsing CSV file: ' + results.errors[0].message);
                        self.resetForm();
                        return;
                    }

                    // Prepare preview data
                    var headers = Object.keys(results.data[0] || {});
                    headers = headers.map(function (header) {
                        return header.toLowerCase();
                    });

                    //check if headers contain Id, entry, lifeStats
                    var requiredHeaders = ['id', 'entry', 'lifestats'];

                    var missingHeaders = false;
                    requiredHeaders.forEach(function (header) {
                        if (!headers.includes(header)) {
                            missingHeaders = true;
                        }
                    });

                    if (missingHeaders) {
                        self.showMessage('error', 'Your CSV is missing some required headers. CSV should include these headers: ' + '<strong>' + requiredHeaders.join(', ') + '</strong>');
                        self.resetForm();
                        return;
                    }

                    var preview_data = results.data.slice(0, 20);
                    self.previewData = {
                        headers: headers,
                        preview_data: preview_data,
                        total_rows: results.data.length
                    };

                    $('#ppr-preview-section').show();
                    self.showMessage('success', 'File ready for preview and upload. Total rows: ' + results.data.length);
                },
                error: function (error) {
                    $('#ppr-loading').hide();
                    $('.ppr-drop-content').show();
                    self.showMessage('error', 'Error reading file: ' + error.message);
                    self.resetForm();
                }
            });
        },

        showPreview: function () {
            if (!this.previewData) return;

            var html = '<table class="ppr-preview-table">';
            html += '<thead><tr>';

            // Headers
            this.previewData.headers.forEach(function (header) {
                html += '<th>' + header.charAt(0).toUpperCase() + header.slice(1) + '</th>';
            });
            html += '</tr></thead><tbody>';

            // Data rows
            this.previewData.preview_data.forEach(function (row) {
                console.log(row);
                html += '<tr>';
                html += '<td>' + (row.Id || '') + '</td>';
                html += '<td>' + (row.entry || '') + '</td>';
                html += '<td>' + (row.lifeStats || '') + '</td>';
                html += '</tr>';
            });

            html += '</tbody></table>';
                html += '<p><em>Showing first ' + this.previewData.preview_data.length + ' rows of ' + this.previewData.total_rows + ' total rows.</em></p>';

            $('#ppr-preview-table').html(html);
            $('#ppr-preview-modal').show();
        },


        showMessage: function (type, message) {
            var messageClass = type === 'success' ? 'notice-success' : 'notice-error';
            var html = '<div class="notice ' + messageClass + ' is-dismissible"><p>' + message + '</p></div>';

            var messagesContainer = $('#ppr-messages');
            messagesContainer.html(html);
            messagesContainer.show();

            var timeout = 5000;
            if (type === 'error') {
                timeout = 10000;
            }

            // Auto-hide after 5 seconds
            setTimeout(function () {
                messagesContainer.fadeOut();
            }, timeout);
        },

        resetForm: function () {
            this.selectedFile = null;
            this.previewData = null;
            $('#ppr-file-input').val('');
            $('#ppr-file-info').hide();
            $('#ppr-options-wrapper').hide();
            $('#ppr-preview-section').hide();
            $('#ppr-preview-modal').hide();
        }
    };


    PPRAdmin.init();
});