/* MinhTB VERSION 2 */

jQuery(function($) {

    /* Load sort manually list */
    $(document).on('change', '#sortby', function() {
        var t = $(this);
        var $sort = $('#sortby').val();
        var $groupid = $('#separate-group').val();
        var $load = $('#sortby').data('load');

        $('.id_order_students').remove();

        if ($sort == 3 && $load == 1) {
            $('#manually-list').removeClass('hidden');
        } else {
            var $url = t.closest('form').attr('action');
            var $id = t.closest('form').find('input[type="hidden"][name="id"]').val();
            $('#separate-group').after('<div class="loading-icon"><i class="fa fa-refresh fa-spin fa-3x fa-fw margin-bottom"></i></div>');

            $.ajax({
                url: $url,
                method: 'post',
                data: {
                    sort: $sort,
                    groupid: $groupid,
                    id: $id
                },
                success: function ($html) {
                    if ($sort == 3) {
                        $('#sortby').data('load', 1);
                    }
                    $('#separate-group').after($html);
                    $('#separate-group').parent().find('.loading-icon').remove();
                }
            });

            if ($sort != 3) {
                $('#manually-list').addClass('hidden');
            }
        }
    });

    $(window).on('load',function() {
        var $sort = $('#sortby').val();
        var $groupid = $('#separate-group').val();

        var $id = $('.sort-form input[type="hidden"][name="id"]').val();
        var $url = $('.sort-form').attr('action');
        $('#separate-group').after('<div class="loading-icon"><i class="fa fa-refresh fa-spin fa-3x fa-fw margin-bottom"></i></div>');

        $.ajax({
            url: $url,
            method: 'post',
            data: {
                sort: $sort,
                groupid: $groupid,
                id: $id
            },
            success: function ($html) {
                if ($sort == 3) {
                    $('#sortby').data('load', 1);
                }
                $('#separate-group').after($html);
                $('#separate-group').parent().find('.loading-icon').remove();
            }
        });
    });

    $(document).on('change', '#separate-group', function() {
        var $url = $(this).closest('form').attr('action');
        var $id = $(this).closest('form').find('input[type="hidden"][name="id"]').val();
        var $groupid = $(this).val();
    	M.core_formchangechecker.set_form_submitted();
        window.location.replace($url + '?id=' + $id + '&groupid=' + $groupid);
    });

});
