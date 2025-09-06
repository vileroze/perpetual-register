jQuery(document).ready(function () {
    var CDMAdmin = {
        selectedFile: null,
        previewData: null,

        init: function () {
            this.bindEvents();
        },

        bindEvents: function () {
            var self = this;

            // Drop zone events
            jQuery(document).on('click', '#ppr-drop-zone', function (e) {
                if (e.target !== this) {
                    return;
                }
                jQuery('#ppr-file-input').click();
            });

            // Drag and drop events
            jQuery(document).on('dragover', '#ppr-drop-zone', function (e) {
                e.preventDefault();
                jQuery(this).addClass('dragover');
            });

            jQuery(document).on('dragleave', '#ppr-drop-zone', function (e) {
                e.preventDefault();
                jQuery(this).removeClass('dragover');
            });

            jQuery(document).on('drop', '#ppr-drop-zone', function (e) {
                e.preventDefault();
                jQuery(this).removeClass('dragover');

                var files = e.originalEvent.dataTransfer.files;
                if (files.length > 0) {
                    self.handleFileSelect(files[0]);
                }
            });

            // File input change
            jQuery(document).on('change', '#ppr-file-input', function () {
                var file = this.files[0];
                if (file) {
                    self.handleFileSelect(file);
                }
            });
        },

        handleFileSelect: function (file) {
            if (!this.validateFile(file)) {
                return;
            }

            this.selectedFile = file;
            this.showFileInfo(file);
        },

        validateFile: function (file) {
            var validTypes = ['text/csv'];
            var validExtensions = ['csv'];
            var fileExtension = file.name.split('.').pop().toLowerCase();

            if (!validTypes.includes(file.type) && !validExtensions.includes(fileExtension)) {
                alert('Invalid file type. Please upload a CSV file.');
                return;
            }

            return true;
        },

        showFileInfo: function (file) {
            var fileInfo = jQuery('#ppr-file-info');
            fileInfo.html(
                '<strong>' + file.name + '</strong><br>' +
                'Size: ' + this.formatFileSize(file.size) + '<br>' +
                'Type: ' + file.type
            ).show();

            jQuery('#ppr-options').show();
        },

        formatFileSize: function (bytes) {
            if (bytes === 0) return '0 Bytes';
            var k = 1024;
            var sizes = ['Bytes', 'KB', 'MB', 'GB'];
            var i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        },
    };


    CDMAdmin.init();
});