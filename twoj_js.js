/**
 * JavaScript dla Zadania 5: MyCloud - Dysk Sieciowy
 * Politechnika Bydgoska - Technologie Multimedialne
 * Jakub Baka
 */

// Dynamiczne ładowanie nagłówka Navbar przez AJAX
function myLoadHeader(retryCount = 0) {
    const $target = $('#myHeader');
    if (!$target.length) return;

    $.ajax({
        url: 'header.php?_t=' + Date.now(),
        type: 'GET',
        cache: false,
        timeout: 4000
    }).done(function(html) {
        if (html && html.trim().length > 0) {
            $target.html(html);
        } else if (retryCount < 3) {
            setTimeout(function() {
                myLoadHeader(retryCount + 1);
            }, 200);
        }
    }).fail(function(xhr, status, error) {
        console.warn('Nieudane załadowanie navbara (próba ' + (retryCount + 1) + '/3):', status, error);
        if (retryCount < 3) {
            setTimeout(function() {
                myLoadHeader(retryCount + 1);
            }, 250);
        }
    });
}

$(document).ready(function() {
    // Obsługa drag-and-drop dla stref uploadu
    const $dropzone = $('.upload-dropzone');
    if ($dropzone.length) {
        $dropzone.on('dragover dragenter', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).addClass('dragover');
        });

        $dropzone.on('dragleave dragend drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).removeClass('dragover');
        });

        $dropzone.on('drop', function(e) {
            const files = e.originalEvent.dataTransfer.files;
            if (files && files.length > 0) {
                const fileInput = $(this).find('input[type="file"]')[0];
                if (fileInput) {
                    fileInput.files = files;
                    $(fileInput).trigger('change');
                }
            }
        });
    }
});
