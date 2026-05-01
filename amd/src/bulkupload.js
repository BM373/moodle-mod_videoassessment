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
 * Bulk upload JavaScript module for Videoassessment
 *
 * @module     mod_videoassessment/bulkupload
 * @package
 * @copyright  2024 Don Hinkleman (hinkelman@mac.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/log'], function ($, log) {

    const PROGRESS_INTERVAL = 1000;
    const conf = { parallels: 2 };
    const str = {
        error: "Error",
        queued: "Queued",
        uploading: "Uploading",
        converting: "Converting",
        complete: "Complete"
    };

    /**
     * DOM helper to get element by ID.
     * @param {string} id The element ID.
     * @return {HTMLElement|null} The DOM element.
     */
    function get(id) {
        return document.getElementById(id);
    }

    /**
     * Create a text node.
     * @param {string} s The string for the text node.
     * @return {Text} The text node.
     */
    function text(s) {
        return document.createTextNode(s);
    }

    /**
     * Clear all child nodes from a DOM element.
     * @param {HTMLElement} e The element to clear.
     */
    function clear(e) {
        while (e.firstChild) {
            e.removeChild(e.firstChild);
        }
    }

    /**
     * Make an AJAX request using XMLHttpRequest.
     *
     * @param {string} method HTTP method (get, post, etc).
     * @param {string} url The target URL.
     * @param {Object} params Parameters to send (key-value pairs).
     * @param {Function} onComplete Callback on completion. Receives (responseText, status).
     * @param {Function} [onProgress] Callback on progress. Receives (loaded, total).
     */
    function ajax(method, url, params, onComplete, onProgress) {
        const data = new FormData();
        if (params) {
            Object.keys(params).forEach(key => data.append(key, params[key]));
        }
        if (typeof M !== "undefined" && M.cfg && M.cfg.sesskey) {
            data.append("sesskey", M.cfg.sesskey);
        }
        data.append("uniq", Date.now());

        const xhr = new XMLHttpRequest();
        xhr.open(method, url, true);

        if (onComplete) {
            xhr.onreadystatechange = () => {
                if (xhr.readyState === 4) {
                    onComplete(xhr.responseText, xhr.status);
                }
            };
        }
        if (onProgress) {
            (xhr.upload || xhr).addEventListener("progress", (e) => {
                onProgress(e.loaded || e.position, e.total || e.totalSize);
            });
        }
        xhr.send(data);
    }

    /**
     * Convert a file size in bytes to a human-readable string.
     *
     * @param {number} size The file size in bytes.
     * @return {string} The human-readable size string.
     */
    function suffix(size) {
        const units = ["B", "KB", "MB", "GB", "TB"];
        let unit = units.shift();
        while (size >= 1024 && units.length) {
            size /= 1024;
            unit = units.shift();
        }
        return `${size.toFixed(1)} ${unit}`;
    }

    class Status {
        constructor(cell) { this.cell = cell; }
        set(status) {
            clear(this.cell);
            this.cell.appendChild(text(str[status]));
            this.cell.parentNode.className = status;
        }
    }
    Status.ERROR = "error";
    Status.QUEUED = "queued";
    Status.UPLOADING = "uploading";
    Status.CONVERTING = "converting";
    Status.COMPLETE = "complete";

    class Progress {
        constructor(cell) {
            const table = document.createElement("table");
            const row = table.insertRow(-1);
            this.scales = Array.from({ length: 50 }, () => row.insertCell(-1));
            cell.appendChild(table);
        }
        set(value) {
            const n = Math.floor(this.scales.length * value / 100);
            this.scales.forEach((scale, index) => {
                scale.className = index < n ? "done" : "";
            });
        }
    }

    const bulkupload = {
        setConfig(name, value) { conf[name] = value; },
        setString(name, value) { str[name] = value; },

        init(cmid) {
            if (!window.File || !window.XMLHttpRequest || !window.FormData) {
                log.debug('Unsupported browser for file upload.');
                return;
            }
            $('#not_supported_browser').hide();

            const tasklist = get("tasklist");
            let queue = [];
            let running = 0;

            const startAll = () => {
                while (running < conf.parallels && queue.length) {
                    queue.shift().start();
                    running++;
                }
            };

            const stopped = () => {
                running--;
                startAll();
            };

            class Task {
                constructor(file, status, progress) {
                    this.file = file;
                    this.status = status;
                    this.progress = progress;
                }
                isCode(code) { return /^[A-Za-z0-9\-\._]+$/.test(code); }
                refresh(code) {
                    ajax("post", "ajax.php", { cmid, code }, (result, status) => {
                        if (status !== 200 || !/^\d+$/.test(result)) {
                            this.status.set(Status.ERROR);
                            stopped();
                        } else {
                            const percent = parseInt(result, 10);
                            this.progress.set(percent);
                            if (percent >= 100) {
                                this.status.set(Status.COMPLETE);
                                stopped();
                            } else {
                                setTimeout(() => this.refresh(code), PROGRESS_INTERVAL);
                            }
                        }
                    });
                }
                start() {
                    ajax("post", "ajax.php", { cmid, file: this.file }, (result, status) => {
                        if (status !== 200 || !this.isCode(result)) {
                            this.status.set(Status.ERROR);
                            stopped();
                        } else {
                            this.status.set(Status.CONVERTING);
                            this.refresh(result);
                        }
                    }, (loaded, total) => {
                        this.progress.set(100 * loaded / total);
                    });
                    this.status.set(Status.UPLOADING);
                }
            }

            const add = (file) => {
                const row = tasklist.insertRow(-1);
                const addCell = (className) => {
                    const cell = row.insertCell(-1);
                    cell.className = className;
                    return cell;
                };
                addCell("filename").appendChild(text(file.name));
                addCell("filesize").appendChild(text(suffix(file.size)));
                addCell("mimetype").appendChild(text(file.type));
                const status = new Status(addCell("status"));
                const progress = new Progress(addCell("progress"));
                queue.push(new Task(file, status, progress));
                status.set(Status.QUEUED);
                startAll();
            };

            // Wire up drag/drop:
            const droparea = get("droparea");
            const cancel = (event) => {
                event.preventDefault();
                event.stopPropagation();
                return false;
            };
            droparea.addEventListener("dragenter", cancel, false);
            droparea.addEventListener("dragover", cancel, false);
            droparea.addEventListener("drop", (event) => {
                Array.from(event.dataTransfer.files).forEach(file => add(file));
                return cancel(event);
            }, false);

            // Wire up file input:
            const fileToUpload = get("fileToUpload");
            fileToUpload.addEventListener("change", (event) => {
                Array.from(event.target.files).forEach(file => add(file));
                return cancel(event);
            }, false);

            droparea.style.display = "block";
        }
    };

    return bulkupload;
});