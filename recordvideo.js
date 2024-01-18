(function() {
	var params = {},
		r = /([^&=]+)=?([^&]*)/g;
	function d(s) {
		return decodeURIComponent(s.replace(/\+/g, ' '));
	}
	var match, search = window.location.search;
	while (match = r.exec(search.substring(1))) {
		params[d(match[1])] = d(match[2]);

		if(d(match[2]) === 'true' || d(match[2]) === 'false') {
			params[d(match[1])] = d(match[2]) === 'true' ? true : false;
		}
	}
	window.params = params;
})();
var recordingDIV = document.querySelector('.recordrtc');
var recordingPlayer = recordingDIV.querySelector('video');
recordingDIV.querySelector('button').onclick = function() {
	var button = this;

	if(button.innerHTML === 'Stop Recording') {
		button.disabled = true;
		button.disableStateWaiting = true;
		setTimeout(function() {
			button.disabled = false;
			button.disableStateWaiting = false;
		}, 2 * 1000);

		button.innerHTML = 'Start Recording';

		function stopStream() {
			if(button.stream && button.stream.stop) {
				button.stream.stop();
				button.stream = null;
			}
		}

		if(button.recordRTC) {
			if(button.recordRTC.length) {
				button.recordRTC[0].stopRecording(function(url) {
					if(!button.recordRTC[1]) {
						button.recordingEndedCallback(url);
						stopStream();
						saveToDiskOrOpenNewTab(button.recordRTC[0]);
						return;
					}
					button.recordRTC[1].stopRecording(function(url) {
						button.recordingEndedCallback(url);
						stopStream();
					});
				});
			}
			else {
				button.recordRTC.stopRecording(function(url) {
					button.recordingEndedCallback(url);
					stopStream();
					saveToDiskOrOpenNewTab(button.recordRTC);
				});
			}
		}

		return;
	}

	button.disabled = true;

	var commonConfig = {
		onMediaCaptured: function(stream) {
			button.stream = stream;
			if(button.mediaCapturedCallback) {
				button.mediaCapturedCallback();
			}

			button.innerHTML = 'Stop Recording';
			button.disabled = false;
		},
		onMediaStopped: function() {
			button.innerHTML = 'Start Recording';

			if(!button.disableStateWaiting) {
				button.disabled = false;
			}
		},
		onMediaCapturingFailed: function(error) {
			commonConfig.onMediaStopped();
		}
	};



	captureVideo(commonConfig);
	button.mediaCapturedCallback = function() {
		button.recordRTC = RecordRTC(button.stream, {
			type: 'video',
			disableLogs: params.disableLogs || false,
			canvas: {
				width: params.canvas_width || 320,
				height: params.canvas_height || 240
			},
			frameInterval: typeof params.frameInterval !== 'undefined' ? parseInt(params.frameInterval) : 20 // minimum time between pushing frames to Whammy (in milliseconds)
		});

		button.recordingEndedCallback = function(url) {
			recordingPlayer.src = null;
			recordingPlayer.srcObject = null;

			recordingPlayer.src = url;

			recordingPlayer.onended = function() {
				recordingPlayer.pause();
				recordingPlayer.src = URL.createObjectURL(button.recordRTC.blob);
			};
		};
		button.recordRTC.startRecording();
	};
};

function captureVideo(config) {
	captureUserMedia({video: true}, function(videoStream) {
		recordingPlayer.srcObject = videoStream;

		config.onMediaCaptured(videoStream);

		videoStream.onended = function() {
			config.onMediaStopped();
		};
	}, function(error) {
		config.onMediaCapturingFailed(error);
	});
}
function captureAudioPlusVideo(config) {
	captureUserMedia({video: true, audio: true}, function(audioVideoStream) {
		recordingPlayer.srcObject = audioVideoStream;

		config.onMediaCaptured(audioVideoStream);
		console.log('captureAudioPlusVideo');
		audioVideoStream.onended = function() {
			config.onMediaStopped();
		};
	}, function(error) {
		console.log("MediaCapturing Failed error--"+ error );
		config.onMediaCapturingFailed(error);
	});
}

function captureUserMedia(mediaConstraints, successCallback, errorCallback) {
	navigator.mediaDevices.getUserMedia(mediaConstraints).then(successCallback).catch(errorCallback);
}

function saveToDiskOrOpenNewTab(recordRTC) {
	if (!recordRTC) return alert('No recording found.');
	this.disabled = true;

	var blob = recordRTC instanceof Blob ? recordRTC : recordRTC.blob;
	var fileType = blob.type.split('/')[0] || 'audio';
	var fileName = (Math.random() * 1000).toString().replace('.', '');

	if (fileType === 'audio') {
		fileName += '.' + (!!navigator.mozGetUserMedia ? 'ogg' : 'wav');
	} else {
		fileName += '.webm';
	}
	var formData = new FormData($("#mform")[0]);
	formData.append('isRecordVideo', 1);
	formData.append(fileType + '-filename', fileName);
	formData.append(fileType, blob);

	var url = $("#mform").attr("action");
	var id = formData.get('id');
	var request = new XMLHttpRequest();
	M.core_formchangechecker.reset_form_dirty_state();
	request.onreadystatechange = function() {
		if (request.readyState == 4 && request.status == 200) {
			window.location.href= url+"?id="+id;
		}
	};
	request.open('POST', url);
	request.send(formData);
}
