define(['jquery', 'core/notification','core/ajax', 'core/modal_factory'],
    function($, notification, ajax, ModalFactory) {

    function Ajaxcall() {
        this.value = "ajax ok";
    };

    Ajaxcall.prototype.getGetallcomments = function(action, userid, timing, cmid, id) {
        var promises = ajax.call([{
            methodname: 'mod_videoassessment_get_getallcomments',
            args: {ajax: 1, action: action, userid: userid, timing: timing, cmid: cmid, id: id},
            fail: notification.exception
        }]);
        promises[0].then(function(data) {
            res = $.parseJSON(data.html);
            if (res) {
                $.when(ModalFactory.create({
                    type: ModalFactory.types.CANCEL,
                    title: "<h1>General Comments</h1>",
                    body: res,
                })).then(function (modal) {
                    modal.show();
                    return false;
                }).catch(notification.exception);
            }
        });
    };
    Ajaxcall.prototype.getcoursesbycategory = function(action, catid, currentcourseid) {
        var promises = ajax.call([{
            methodname: 'mod_videoassessment_get_coursesbycategory',
            args: {ajax: 1, action: action, catid: catid, currentcourseid: currentcourseid},
            fail: notification.exception
        }]);
        promises[0].then(function(data) {
            //res = $.parseJSON(data.html);
            if (data.html) {
                $('#publish-course').html(data.html);
                $('#publish-course').trigger('change');
            }
        });
    };

    Ajaxcall.prototype.getsectionsbycourse = function(action, courseid, currentsectionid) {
        var promises = ajax.call([{
            methodname: 'mod_videoassessment_get_sectionsbycourse',
            args: {ajax: 1, action: action, courseid: courseid, currentsectionid: currentsectionid},
            fail: notification.exception
        }]);
        promises[0].then(function(data) {
            //res = $.parseJSON(data.html);
            if (data.html) {
                $('#publish-section').html(data.html);
                $('#publish-section').removeAttr('disabled');
            } else {
                $('#publish-section').attr('disabled', 'disabled');
            }
        });
    };

    Ajaxcall.prototype.assignclasssortgroup = function(action, sort, groupid, id) {
        var promises = ajax.call([{
            methodname: 'mod_videoassessment_assignclass_sort_group',
            args: {action: action, sort: sort, groupid: groupid, id: id},
            fail: notification.exception
        }]);
        promises[0].then(function(data) {
            //res = $.parseJSON(data.html);
            if (sort == 3) {
                $('#sortby').data('load', 1);
            }
            $('#separate-group').after(data.html);
            $('#separate-group').parent().find('.loading-icon').remove();
        });
    };

    return Ajaxcall;
});