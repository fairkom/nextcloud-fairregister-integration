// SPDX-FileCopyrightText: fairkom <philipp.monz@fairkom.eu>
// SPDX-License-Identifier: AGPL-3.0-or-later
const path = require('path')
const webpackConfig = require('@nextcloud/webpack-vue-config')

webpackConfig.entry = {
	filesAction: path.join(__dirname, 'src', 'filesAction.js'),
	adminSettings: path.join(__dirname, 'src', 'adminSettings.js'),
}

module.exports = webpackConfig
