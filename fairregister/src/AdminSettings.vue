<!--
SPDX-FileCopyrightText: fairkom <philipp.monz@fairkom.eu>
SPDX-License-Identifier: AGPL-3.0-or-later
-->
<template>
	<NcSettingsSection :name="t('fairregister', 'fairregister')"
		:description="t('fairregister', 'Configure where users are sent to finish registering a Nextcloud file. The plugin creates a short-lived public link for the file and redirects the user to the fairregister frontend, which downloads the file and runs the metadata form. No OAuth / OIDC client setup needed on this Nextcloud — the frontend handles authentication on its own.')">
		<div class="presets">
			<span class="muted">{{ t('fairregister', 'Quick presets:') }}</span>
			<NcButton type="secondary" @click="state.frontend_url = state.presets.prod">
				{{ t('fairregister', 'Production') }}
			</NcButton>
			<NcButton type="secondary" @click="state.frontend_url = state.presets.dev">
				{{ t('fairregister', 'Development') }}
			</NcButton>
		</div>

		<div class="field">
			<label for="fr-fe-url">{{ t('fairregister', 'Frontend base URL') }}</label>
			<input id="fr-fe-url" v-model="state.frontend_url" type="url"
				placeholder="https://fairregister.example">
			<small>{{ t('fairregister', 'Required. Where the file action sends users after creating a share link. Leave empty to disable the action.') }}</small>
		</div>

		<NcButton type="primary" @click="save">
			<template #icon><IconContentSave :size="20" /></template>
			{{ t('fairregister', 'Save') }}
		</NcButton>
	</NcSettingsSection>
</template>

<script>
import axios from '@nextcloud/axios'
import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'
import { showSuccess, showError } from '@nextcloud/dialogs'
import { translate as t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcSettingsSection from '@nextcloud/vue/components/NcSettingsSection'
import IconContentSave from 'vue-material-design-icons/ContentSave.vue'

export default {
	name: 'AdminSettings',
	components: { NcButton, NcSettingsSection, IconContentSave },
	data() {
		return { state: loadState('fairregister', 'admin-config') }
	},
	methods: {
		t,
		async save() {
			try {
				await axios.post(generateUrl('/apps/fairregister/admin-config'), {
					frontend_url: this.state.frontend_url,
				})
				showSuccess(t('fairregister', 'Saved'))
			} catch (e) {
				showError(t('fairregister', 'Save failed'))
			}
		},
	},
}
</script>

<style scoped>
.presets {
	display: flex;
	align-items: center;
	gap: 8px;
	flex-wrap: wrap;
	margin-bottom: 24px;
	padding: 8px 12px;
	background: var(--color-background-hover);
	border-radius: var(--border-radius);
	max-width: 520px;
}
.presets .muted { color: var(--color-text-maxcontrast); }
.field { margin-bottom: 16px; display: flex; flex-direction: column; max-width: 520px; }
.field label { font-weight: bold; margin-bottom: 4px; }
.field input { padding: 8px; border: 1px solid var(--color-border); border-radius: var(--border-radius); background: var(--color-main-background); color: var(--color-main-text); }
.field small { margin-top: 4px; color: var(--color-text-maxcontrast); }
</style>
