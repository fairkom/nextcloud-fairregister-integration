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
				continueWindow.document.write(
					'<!doctype html><html lang="en"><head><meta charset="utf-8">'
					+ '<title>fairregister</title>'
					+ '<style>'
					+ 'html,body{height:100%;margin:0;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:#f5f7fa;color:#222}'
					+ 'body{display:flex;align-items:center;justify-content:center}'
					+ '.box{text-align:center;padding:32px;max-width:380px}'
					+ '.spin{width:42px;height:42px;border:3px solid #cfd8e6;border-top-color:#0082c9;border-radius:50%;margin:0 auto 18px;animation:s 0.9s linear infinite}'
					+ '@keyframes s{to{transform:rotate(360deg)}}'
					+ '.fn{font-family:ui-monospace,Menlo,monospace;background:#e9eef5;padding:2px 6px;border-radius:4px;word-break:break-all}'
					+ '</style></head><body>'
					+ '<div class="box">'
					+ '<div class="spin" aria-hidden="true"></div>'
					+ '<div><strong>Preparing for fairregister…</strong></div>'
					+ '<div style="margin-top:8px;color:#666;font-size:14px"><span class="fn">'
					+ String(node.basename).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]))
					+ '</span></div>'
					+ '</div></body></html>',
				)
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
