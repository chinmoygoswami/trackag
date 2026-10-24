<div class="modal fade" id="orderImagesModal" tabindex="-1" aria-labelledby="orderImagesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="orderImagesModalLabel">Order Images</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="order-images-message" class="alert d-none" role="status"></div>
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
    const modal = $('#orderImagesModal');
    const gallery = $('#order-images-gallery');
    const message = $('#order-images-message');

    function showMessage(text) {
        message.removeClass('d-none').addClass('alert-danger').text(text);
    }

    function errorText(xhr) {
        const body = xhr.responseJSON || {};
        return body.errors ? Object.values(body.errors).flat().join(' ')
            : (body.message || 'Unable to load images. Please try again.');
    }

    function loadImages(url) {
        if (listRequest) listRequest.abort();
        gallery.empty().append($('<p class="text-muted">').text('Loading images…'));
        listRequest = $.ajax({
            url: url,
            headers: { Accept: 'application/json' },
            success: function (res) {
                if (activeUrl !== url) return;
                gallery.empty();
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
        message.addClass('d-none').text('');
        modal.modal('show');
        loadImages(activeUrl);
    });

});
</script>
@endpush
