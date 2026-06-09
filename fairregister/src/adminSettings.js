// SPDX-FileCopyrightText: fairkom <philipp.monz@fairkom.eu>
// SPDX-License-Identifier: AGPL-3.0-or-later
import { createApp } from 'vue'
import { generateFilePath } from '@nextcloud/router'
import AdminSettings from './AdminSettings.vue'

// eslint-disable-next-line camelcase, no-undef
__webpack_public_path__ = generateFilePath('fairregister', '', 'js/')

createApp(AdminSettings).mount('#fairregister_admin_settings')
