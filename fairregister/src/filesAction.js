// SPDX-FileCopyrightText: fairkom <philipp.monz@fairkom.eu>
// SPDX-License-Identifier: AGPL-3.0-or-later
import { registerFileAction, FileAction } from '@nextcloud/files'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { showError, showSuccess, showInfo } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'
import { mdiStamper } from '@mdi/js'

const stampIcon = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="${mdiStamper}"/></svg>`

// Tracks file-ids whose upload is currently in flight, so double-clicking
// the action doesn't fire two POSTs (which would land two presigned PUTs
// + two future Works once the user fills out the frontend twice).
const inFlight = new Set()

registerFileAction(new FileAction({
	id: 'fairregister-register',
	displayName: () => t('fairregister', 'Register with fairregister'),
	iconSvgInline: () => stampIcon,
	enabled(nodes) {
		return nodes.length === 1 && nodes[0].type === 'file'
	},
	async exec(node) {
		if (inFlight.has(node.fileid)) {
			showInfo(t('fairregister', 'Upload of "{name}" is already in progress.', { name: node.basename }))
			return null
		}
		inFlight.add(node.fileid)

		// Open the new tab SYNCHRONOUSLY in the click handler so the popup
		// blocker doesn't intercept it. Navigate later when we have the URL.
		// While we wait for the plugin to mint a transfer token, render a
		// small loading screen so the user does not stare at about:blank.
		const continueWindow = window.open('about:blank', '_blank')
		if (continueWindow && !continueWindow.closed) {
			try {
				const escFilename = String(node.basename).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]))
				continueWindow.document.write(`<!doctype html><html lang="en"><head><meta charset="utf-8">
<title>fairregister</title>
<style>
  html,body{height:100%;margin:0;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:#f5f7fa;color:#222}
  body{display:flex;align-items:center;justify-content:center}
  .box{text-align:center;padding:32px;max-width:420px}

  /* animation stage: stamp + impact ring + paper line */
  .stage{position:relative;width:140px;height:140px;margin:0 auto 26px}

  /* the stamp — handle + base, comes down repeatedly */
  .stamp{
    position:absolute;left:50%;top:6px;transform:translateX(-50%) translateY(0);
    width:74px;height:96px;color:#003c8f;
    animation:stamp 1.6s cubic-bezier(.7,0,.6,1.4) infinite;
  }
  .stamp svg{width:100%;height:100%;fill:currentColor;display:block}

  /* paper line at the bottom of the stage */
  .paper{
    position:absolute;left:6%;right:6%;bottom:18px;height:6px;border-radius:3px;
    background:linear-gradient(180deg,#e3e9f1 0%,#cfd8e6 100%);
    box-shadow:0 2px 6px rgba(0,0,0,.06);
  }

  /* impact ring that pulses on each strike */
  .impact{
    position:absolute;left:50%;bottom:18px;transform:translate(-50%,-50%) scale(0);
    width:18px;height:18px;border-radius:50%;
    border:2px solid #9cc02b;opacity:0;
    animation:impact 1.6s ease-out infinite;
  }

  @keyframes stamp{
    0%   { transform:translateX(-50%) translateY(0); }
    35%  { transform:translateX(-50%) translateY(-4px); }
    55%  { transform:translateX(-50%) translateY(28px); }   /* strike! */
    62%  { transform:translateX(-50%) translateY(26px); }
    78%  { transform:translateX(-50%) translateY(0); }
    100% { transform:translateX(-50%) translateY(0); }
  }
  @keyframes impact{
    0%, 50%   { transform:translate(-50%,-50%) scale(0); opacity:0; }
    58%       { transform:translate(-50%,-50%) scale(0.6); opacity:0.85; }
    100%      { transform:translate(-50%,-50%) scale(3); opacity:0; }
  }

  h1{margin:0 0 6px;font-size:18px;color:#1a1a1a}
  .fn{font-family:ui-monospace,Menlo,monospace;background:#e9eef5;padding:3px 8px;border-radius:4px;word-break:break-all;font-size:14px}
  .muted{color:#666;font-size:13px;margin-top:12px}
  @media (prefers-reduced-motion: reduce){
    .stamp, .impact { animation:none }
  }
</style></head><body>
<div class="box">
  <div class="stage" aria-hidden="true">
    <div class="stamp">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
        <path d="M12,3A3,3 0 0,0 9,6C9,9 14,13 6,13A2,2 0 0,0 4,15V17H20V15A2,2 0 0,0 18,13C10,13 15,9 15,6C15,4 13.66,3 12,3M6,19V21H18V19H6Z"/>
      </svg>
    </div>
    <div class="impact"></div>
    <div class="paper"></div>
  </div>
  <h1>Preparing for fairregister…</h1>
  <div><span class="fn">${escFilename}</span></div>
  <div class="muted">Opening fairregister to finish the registration.</div>
</div>
</body></html>`)
				continueWindow.document.close()
			} catch {
				/* cross-origin / closed — ignore, we'll just navigate when ready */
			}
		}
		showInfo(t('fairregister', 'Preparing "{name}" for fairregister…', { name: node.basename }))

		try {
			const { data } = await axios.post(generateUrl('/apps/fairregister/works/register'), {
				ncFileId: node.fileid,
			})
			if (!data.continueUrl) {
				throw new Error('Plugin did not return a continueUrl')
			}
			if (continueWindow && !continueWindow.closed) {
				continueWindow.location.href = data.continueUrl
			} else {
				// popup was blocked, navigate current tab as fallback
				window.location.href = data.continueUrl
			}
			showSuccess(t('fairregister', 'Continue registration in the new tab.'))
			return true
		} catch (e) {
			if (continueWindow && !continueWindow.closed) {
				continueWindow.close()
			}
			const code = e.response?.data?.code
			const reason = e.response?.data?.error || ''
			if (code === 'frontend_not_configured') {
				showError(t('fairregister', 'fairregister frontend URL not configured. Ask the admin.'))
			} else {
				showError(t('fairregister', 'Register failed: {msg}', { msg: reason || e.message }))
			}
			return null
		} finally {
			inFlight.delete(node.fileid)
		}
	},
	// no `default` → action appears in the three-dots ("More") menu,
	// not as an inline primary action on the row
	order: 50,
}))
