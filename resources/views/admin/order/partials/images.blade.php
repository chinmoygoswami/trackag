<div class="modal fade" id="orderImagesModal" tabindex="-1" aria-labelledby="orderImagesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="orderImagesModalLabel">Order Images</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="order-images-message" class="alert d-none" role="status"></div>
                <form id="order-images-form" enctype="multipart/form-data" class="mb-4 d-none">
                    @csrf
                    <label for="order-images-files" class="form-label">Upload Images</label>
                    <input type="file" id="order-images-files" name="images[]" class="form-control"
                        accept="image/jpeg,image/png,image/webp" multiple required>
                    <div id="order-images-limits" class="form-text"></div>
                    <button type="submit" id="order-images-upload" class="btn btn-primary mt-2">Upload Images</button>
                </form>
                <div id="order-images-gallery" class="row g-3"></div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
$(function () {
    let activeUrl = null;
    let activeButton = null;
    let listRequest = null;
    let uploading = false;
    let imageCount = 0;
    let imageLimit = 0;
    const modal = $('#orderImagesModal');
    const gallery = $('#order-images-gallery');
    const form = $('#order-images-form');
    const message = $('#order-images-message');

    function showMessage(text, success = false) {
        message.removeClass('d-none alert-danger alert-success')
            .addClass(success ? 'alert-success' : 'alert-danger').text(text);
    }

    function errorText(xhr) {
        const body = xhr.responseJSON || {};
        return body.errors ? Object.values(body.errors).flat().join(' ')
            : (body.message || (xhr.status === 413 ? 'The selected upload is too large.' : 'Unable to complete the request. Please try again.'));
    }

    function loadImages(url) {
        if (listRequest) listRequest.abort();
        gallery.empty().append($('<p class="text-muted">').text('Loading images…'));
        form.addClass('d-none');
        listRequest = $.ajax({
            url: url,
            headers: { Accept: 'application/json' },
            success: function (res) {
                if (activeUrl !== url) return;
                gallery.empty();
                imageCount = res.images.length;
                imageLimit = res.max_images;
                activeButton.find('.order-images-count').text(res.images.length);
                if (!res.images.length) gallery.append($('<p class="text-muted">').text('No images uploaded for this order.'));
                res.images.forEach(function (image) {
                    const link = $('<a>', { href: image.url, target: '_blank', rel: 'noopener' });
                    link.append($('<img>', {
                        src: image.url, alt: image.name, loading: 'lazy', class: 'img-fluid rounded',
                        style: 'width:100%;height:200px;object-fit:contain'
                    }));
                    gallery.append($('<div class="col-6 col-md-4 col-lg-3">').append(link)
                        .append($('<div class="small text-muted text-break mt-1">').text(image.name)));
                });
                $('#order-images-limits').text('JPEG, PNG or WebP. Up to ' + res.max_images + ' images per order, '
                    + (res.max_file_size_kb / 1024) + ' MB each. New uploads keep existing images.');
                form.removeClass('d-none');
                $('#order-images-upload').prop('disabled', uploading || imageCount >= imageLimit);
            },
            error: function (xhr, status) {
                if (status === 'abort' || activeUrl !== url) return;
                gallery.empty();
                showMessage(errorText(xhr));
            }
        });
    }

    $(document).on('click', '.order-images-btn', function () {
        activeButton = $(this);
        activeUrl = activeButton.data('url');
        $('#orderImagesModalLabel').text('Order Images — ' + activeButton.data('order-no'));
        form[0].reset();
        message.addClass('d-none').text('');
        modal.modal('show');
        loadImages(activeUrl);
    });

    // Keep the current order selected until its upload has finished.
    modal.on('hide.bs.modal', function (event) {
        if (uploading) event.preventDefault();
    });

    form.on('submit', function (event) {
        event.preventDefault();
        if (uploading || !activeUrl) return;
        uploading = true;
        message.addClass('d-none').text('');
        $('#order-images-upload').prop('disabled', true).text('Uploading…');
        $.ajax({
            url: activeUrl, type: 'POST', data: new FormData(this),
            processData: false, contentType: false,
            headers: { Accept: 'application/json' },
            success: function (res) {
                form[0].reset();
                showMessage(res.message, true);
                loadImages(activeUrl);
            },
            error: function (xhr) { showMessage(errorText(xhr)); },
            complete: function () {
                uploading = false;
                $('#order-images-upload').prop('disabled', imageCount >= imageLimit).text('Upload Images');
            }
        });
    });
});
</script>
@endpush
