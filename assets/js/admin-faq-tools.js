(function(){
	'use strict';

	/* ── Export: toggle global options vs single-group hint ── */
	var expScope  = document.getElementById('nlf-faq-export-scope');
	var expGlobal = document.getElementById('nlf-export-global-opts');
	var expHint   = document.getElementById('nlf-export-group-hint');
	if(expScope && expGlobal && expHint){
		expScope.addEventListener('change',function(){
			var isAll = this.value === 'all';
			expGlobal.style.display = isAll ? '' : 'none';
			expHint.style.display   = isAll ? 'none' : '';
		});
	}

	/* ── Import: toggle options based on target ── */
	var impTarget    = document.getElementById('nlf-faq-import-target');
	var impGroupOps  = document.getElementById('nlf-import-group-opts');
	var impReplaceOp = document.getElementById('nlf-import-replace-opt');
	var impDupHint   = document.getElementById('nlf-import-duplicate-hint');
	if(impTarget && impGroupOps && impReplaceOp && impDupHint){
		impTarget.addEventListener('change',function(){
			var v = this.value;
			var isGroup = v !== 'all' && v !== 'duplicate';
			var isDup   = v === 'duplicate';
			impGroupOps.style.display  = isGroup ? '' : 'none';
			impReplaceOp.style.display = isDup ? 'none' : '';
			impDupHint.style.display   = isDup ? '' : 'none';
		});
	}

	/* ── File upload zone UX ── */
	var zone     = document.getElementById('nlf-file-zone');
	var fileInfo = document.getElementById('nlf-file-info');
	var fileInp  = document.getElementById('nlf-faq-import-file');
	var fileName = document.getElementById('nlf-file-name');
	var fileSize = document.getElementById('nlf-file-size');
	var fileRem  = document.getElementById('nlf-file-remove');

	function formatBytes(bytes) {
		if (bytes === 0) return '0 Bytes';
		var k = 1024, sizes = ['Bytes','KB','MB'];
		var i = Math.floor(Math.log(bytes) / Math.log(k));
		return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
	}

	function showFileInfo() {
		if (fileInp && fileInp.files && fileInp.files.length && fileName && fileSize && zone && fileInfo) {
			var f = fileInp.files[0];
			fileName.textContent = f.name;
			fileSize.textContent = formatBytes(f.size);
			zone.style.display = 'none';
			fileInfo.classList.add('is-visible');
		}
	}

	function clearFile() {
		if (fileInp) fileInp.value = '';
		if (zone) zone.style.display = '';
		if (fileInfo) fileInfo.classList.remove('is-visible');
	}

	if (fileInp) {
		fileInp.addEventListener('change', showFileInfo);
	}
	if (fileRem) {
		fileRem.addEventListener('click', clearFile);
	}

	/* Drag & drop visual feedback */
	if (zone) {
		['dragenter','dragover'].forEach(function(evt){
			zone.addEventListener(evt, function(e){
				e.preventDefault();
				zone.classList.add('is-dragover');
			});
		});
		['dragleave','drop'].forEach(function(evt){
			zone.addEventListener(evt, function(e){
				e.preventDefault();
				zone.classList.remove('is-dragover');
			});
		});
	}
})();
