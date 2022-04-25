
define(['jquery', 'core/yui', 'core/str', 'core/modal_factory', 'core/notification','core/modal_events','core/ajax'], function($, Y,Str, ModalFactory,ModalEvents, Notification,Ajax) {
    return {
        mobileshowallcomment: function() {
            $('.commentbutton').click(function (){
                var url = '/mod/videoassessment/view.php';
                var timing = $(this).attr('timing');
                var userid = $(this).attr('userid');
                var cmid = $(this).attr('cmid');
                var id = $(this).attr('id');
                $.ajax({
                    method: 'post',
                    url: url,
                    data: {
                        ajax: 1,
                        userid: userid,
                        timing: timing,
                        cmid: cmid,
                        id: id,
                        action: 'getallcomments',
                    },
                    success: function (data) {
                        data = $.parseJSON(data);
                        if (data.html) {
                            $.when(ModalFactory.create({
                                type: ModalFactory.types.CANCEL,
                                title: "<h1>General Comments</h1>",
                                body: data.html,
                            })).then(function (modal){
                                modal.show();
                                return false;
                            }).catch(Notification.exception);
                        }
                    }
                });
            });
        },


        init_message_sent_window:function (messageSent){
            if(messageSent == 1){
                var modal = $.when(ModalFactory.create({
                    type: ModalFactory.types.DEFAULT,
                    title: "",
                    body: '<h2>Notification Message sent</h2>',
                })).then(function (modal){
                    modal.show();
                    setInterval(function (){
                        modal.hide();
                    },2000);
                    return false;
                }).catch(Notification.exception);
            }
        },
        init_upload_file_step:function (){

        },
        init_mobile_upload_progress_bar:function (){
            $("#id_submitbutton").click(function (e){
                if($('input[name="upload"]:checked ').val() == 1){
                    window.onbeforeunload = null;
                    $("#mobileform").submit();
                }else {
                    window.onbeforeunload = null;
                    e.preventDefault();
                    var url = $("#mobileform").attr("action");
                    var formData = new FormData($("#mobileform")[0]);
                    var id = formData.get('id');
                    var submit = function (){
                        $.when($.ajax({
                            url:  url,
                            type:  'POST' ,
                            Accept: 'text/html;charset=UTF-8' ,
                            cache:  false ,
                            data:  new FormData($("#mobileform")[0]),
                            processData:  false ,
                            contentType:  false ,
                            xhr: function(){
                                myXhr = new XMLHttpRequest();
                                if (myXhr.upload){
                                    myXhr.upload.addEventListener( 'progress' , function(evt){
                                        $('.upload-progress').show();
                                    },  false );
                                }
                                return  myXhr;
                            }})).done(function (data){
                            if(data.action !== ""){
                                window.location.href= url+"?action="+data.action+"&id="+id;
                            }
                            window.location.href= url+"?id="+id;
                        });
                    }

                    if($("#id_mobilevideo")[0].files[0]==undefined){
                        $('#id_error_mobilevideo').html('Please upload a video');
                        $('#id_error_mobilevideo').attr('style','display: block');
                        return false;
                    }
                    if($("#id_mobilevideo")[0].files[0].size>500000000) {

                        var modalPromise = Str.get_strings([
                            {key: 'upoladmessage', component: 'videoassessment'},
                            {key: 'upload', component: 'moodle'},
                            {key: 'cancel', component: 'moodle'},
                        ]).then(function(strings) {
                            return Y.use('moodle-core-notification-confirm', function() {
                                var modal = new M.core.confirm({
                                    centered :  true,
                                    question : strings[0],
                                    //question: 'あなたのビデオファイルは100MBを超えています。低い解像度でビデオを撮り直すか、このオリジナルファイルがアップロードされるまで数分お待ちください。',
                                    yesLabel: M.util.get_string('yes', 'moodle'),
                                    noLabel: M.util.get_string('cancel', 'moodle'),
                                    modal: true
                                });
                                modal.on('complete-yes', function (){
                                    modal.hide();
                                });
                                modal.show();
                            });
                        }).catch(Notification.exception);

                    }else{
                        submit();
                    }
                }
            });
        }
    };

});
