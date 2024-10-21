/* MinhTB VERSION 2 03-03-2016 */
jQuery(function($) {

    $('#publish-category').change(function() {
        var catid = $(this).val();
        var url = $(this).closest('form').attr('action');
        var currentcourseid = $('#publish-course').val();

        $.ajax({
            method: 'post',
            url: url,
            data: {
                ajax: 1,
                catid: catid,
                currentcourseid: currentcourseid,
                action: 'getcoursesbycategory',
            },
            success: function(data) {
                data = $.parseJSON(data);

                if (data.html) {
                    $('#publish-course').html(data.html);
                    $('#publish-course').trigger('change');
                }
            }
        });
    }).change();

    $('#publish-course').change(function() {
        var courseid = $(this).val();
        var url = $(this).closest('form').attr('action');
        var currentsectionid = $('#publish-section').val();

        if (courseid != 0) {
            $('#publish-fullname').attr('disabled', 'disabled');
            $('#publish-shortname').attr('disabled', 'disabled');

            $.ajax({
                method: 'post',
                url: url,
                data: {
                    ajax: 1,
                    courseid: courseid,
                    currentsectionid: currentsectionid,
                    action: 'getsectionsbycourse',
                },
                success: function (data) {
                    data = $.parseJSON(data);

                    if (data.html) {
                        $('#publish-section').html(data.html);
                        $('#publish-section').removeAttr('disabled');
                    } else {
                        $('#publish-section').attr('disabled', 'disabled');
                    }
                }
            });

            $('#publish-fullname').attr('disabled', 'disabled').val('');
            $('#publish-shortname').attr('disabled', 'disabled').val('');
        } else {
            $('#publish-section').attr('disabled', 'disabled').html('');
            $('#publish-fullname').removeAttr('disabled');
            $('#publish-shortname').removeAttr('disabled');
        }
    }).change();

    $(document).on('change', '.video-check', function() {
        var check = $(this).prop('checked');
        var count = $('#video-count').val();

        if (check) {
            count++;
        } else {
            count--;
        }

        $('#video-count').val(count);
    });

    $(document).on('change', '#all-video-check', function() {
        var check = $(this).prop('checked');
        var count;

        if (check) {
            count = $('.video-check').size();
        } else {
            count = 0;
        }

        $('#video-count').val(count);
    });

});
