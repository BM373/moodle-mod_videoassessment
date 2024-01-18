/**
 * Video assessment
 *
 * @package videoassessment
 * @author  VERSION2 Inc.
 * @version $Id: script.js 823 2012-09-27 05:28:21Z yama $
 */
if (!M.mod_videoassessment_bulkupload) M.mod_videoassessment_bulkupload = new function ()
{
	var PROGRESS_INTERVAL = 1000;

	var conf = {
		parallels : 2
	};
	this.setConfig = function (name, value) { conf[name] = value; };

	var str = {
		error      : "Error",
		queued     : "Queued",
		uploading  : "Uploading",
		converting : "Converting",
		complete   : "Complete"
	};
	this.setString = function (name, value) { str[name] = value; };


	if (!window.File || !window.XMLHttpRequest || !window.FormData)
		return false;

	get("not_supported_browser").style.display = "none";


	function get(id) { return document.getElementById(id); }
	function text(s) { return document.createTextNode(s); }

	function clear(e) { while (e.hasChildNodes()) e.removeChild(e.lastChild); }

	function ajax(method, url, params, oncomplete, onprogress)
	{
		var data = new FormData();
		if (params) {
			for (var i in params)
				data.append(i, params[i]);
		}
		data.append("uniq", new Date().getTime());

		var xhr = new XMLHttpRequest();
		xhr.open(method, url, true);
		if (oncomplete) {
			xhr.onreadystatechange = function ()
			{
				if (xhr.readyState == 4)
					oncomplete.call(xhr, xhr.responseText);
			};
		}
		if (onprogress) {
			(xhr.upload || xhr).addEventListener("progress", function (e)
			{
				onprogress.call(xhr, e.loaded || e.position, e.total || e.totalSize);
			});
		}
		xhr.send(data);
	}

	function suffix(size)
	{
		var units = [ "B", "KB", "MB", "GB", "TB" ];
		while (size >= 1024 && units.length >= 2) {
			size /= 1024;
			units.shift();
		}
		return size.toFixed(1) + units.shift();
	}


	function Status(cell)
	{
		this.set = function (status)
		{
			clear(cell);
			cell.appendChild(text(str[status]));
			cell.parentNode.className = status;
		};
	}
	Status.ERROR      = "error";
	Status.QUEUED     = "queued";
	Status.UPLOADING  = "uploading";
	Status.CONVERTING = "converting";
	Status.COMPLETE   = "complete";

	function Progress(cell)
	{
		var table = document.createElement("table");
		var row = table.insertRow(-1);
		var scales = [];
		for (var i = 0; i < 50; i++)
			scales.push(row.insertCell(-1));
		cell.appendChild(table);

		this.set = function (value)
		{
			var n = Math.floor(scales.length * parseInt(value) / 100);
			for (var i = 0; i < scales.length; i++)
				scales[i].className = i < n ? "done" : "";
		};
	}

	var tasks = new function ()
	{
		var tasklist = get("tasklist"), cmid = /cmid=(\d+)/.exec(location.href)[1];

		var queue = [], running = 0;

		this.startAll = function ()
		{
			for ( ; running < conf.parallels && queue.length; running++)
				queue.shift().start();
		};

		function stopped()
		{
			running--;
			tasks.startAll();
		}

		function Task(file, status, progress)
		{
			function isCode(code)
			{
				return /^[A-Za-z0-9\-\._]+$/.test(code);
			}

			function refresh(code)
			{
				ajax("post", "ajax.php", { cmid: cmid, code: code }, function (result)
				{
					if (this.status != 200 || !/^\d+$/.test(result)) {
						status.set(Status.ERROR);
						stopped();
					} else {
						progress.set(result);
						if (parseInt(result) >= 100) {
							status.set(Status.COMPLETE);
							stopped();
						} else {
							setTimeout(function () { refresh(code); }, PROGRESS_INTERVAL);
						}
					}
				});
			}

			this.start = function ()
			{
				ajax("post", "ajax.php", { cmid: cmid, file: file }, function (result)
				{
					if (this.status != 200 || !isCode(result)) {
						status.set(Status.ERROR);
						stopped();
					} else {
						status.set(Status.CONVERTING);
						refresh(result);
					}
				}, function (loaded, total)
				{
					progress.set(100 * loaded / total);
				});
				status.set(Status.UPLOADING);
			};
		}

		this.add = function (file)
		{
			var row = tasklist.insertRow(-1);
			function addCell(className)
			{
				var cell = row.insertCell(-1);
				cell.className = className;
				return cell;
			}

			addCell("filename").appendChild(text(file.name));
			addCell("filesize").appendChild(text(suffix(file.size)));
			addCell("mimetype").appendChild(text(file.type));
			var status = new Status(addCell("status"));
			var progress = new Progress(addCell("progress"));

			queue.push(new Task(file, status, progress));
			status.set(Status.QUEUED);

			this.startAll();
		};
	};

	var droparea = get("droparea");

	function cancel(event)
	{
		event.preventDefault && event.preventDefault();
		event.stopPropagation && event.stopPropagation();
		return false;
	}
	droparea.addEventListener("dragenter", cancel, false);
	droparea.addEventListener("dragover", cancel, false);
	droparea.addEventListener("drop", function (event)
	{
		var files = event.dataTransfer.files;
		for (var i = 0; i < files.length; i++) {
			tasks.add(files[i]);
		}
		return cancel(event);
	}, false);

    //Begin add file select button by hungpq
    var fileToUpload = get("fileToUpload");
    fileToUpload.addEventListener("change", function (event)
    {
        var files = event.target.files;
        for (var i = 0; i < files.length; i++) {
            tasks.add(files[i]);
        }
        return cancel(event);
    }, false);
    //End add upload file by hungpq

	droparea.style.display = "block";
};
