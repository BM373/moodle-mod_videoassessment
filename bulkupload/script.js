// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Video assessment
 *
 * @package    mod_videoassessment
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 */

if (!M.mod_videoassessment_bulkupload) M.mod_videoassessment_bulkupload = new function ()
{
    let PROGRESS_INTERVAL = 1000;

    let conf = {
        parallels : 2
    };
    this.setConfig = function (name, value) { conf[name] = value; };

    let str = {
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
        let data = new FormData();
        if (params) {
            for (let i in params)
                data.append(i, params[i]);
        }
        data.append("uniq", new Date().getTime());

        let xhr = new XMLHttpRequest();
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
        let units = [ "B", "KB", "MB", "GB", "TB" ];
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
        let table = document.createElement("table");
        let row = table.insertRow(-1);
        let scales = [];
        for (let i = 0; i < 50; i++)
            scales.push(row.insertCell(-1));
        cell.appendChild(table);

        this.set = function (value)
        {
            let n = Math.floor(scales.length * parseInt(value) / 100);
            for (let i = 0; i < scales.length; i++)
                scales[i].className = i < n ? "done" : "";
        };
    }

    let tasks = new function ()
    {
        let tasklist = get("tasklist"), cmid = /cmid=(\d+)/.exec(location.href)[1];

        let queue = [], running = 0;

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
            let row = tasklist.insertRow(-1);
            function addCell(className)
            {
                let cell = row.insertCell(-1);
                cell.className = className;
                return cell;
            }

            addCell("filename").appendChild(text(file.name));
            addCell("filesize").appendChild(text(suffix(file.size)));
            addCell("mimetype").appendChild(text(file.type));
            let status = new Status(addCell("status"));
            let progress = new Progress(addCell("progress"));

            queue.push(new Task(file, status, progress));
            status.set(Status.QUEUED);

            this.startAll();
        };
    };

    let droparea = get("droparea");

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
        let files = event.dataTransfer.files;
        for (let i = 0; i < files.length; i++) {
            tasks.add(files[i]);
        }
        return cancel(event);
    }, false);

    //Begin add file select button by hungpq
    let fileToUpload = get("fileToUpload");
    fileToUpload.addEventListener("change", function (event)
    {
        let files = event.target.files;
        for (let i = 0; i < files.length; i++) {
            tasks.add(files[i]);
        }
        return cancel(event);
    }, false);
    //End add upload file by hungpq

    droparea.style.display = "block";
};
