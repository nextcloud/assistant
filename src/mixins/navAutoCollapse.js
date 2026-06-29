/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

// The assistant lives in a resizable/draggable dialog, so the navigation
// visibility follows the dialog/container width rather than the viewport.
const NAV_COLLAPSE_BREAKPOINT = 900

/**
 * Collapses the `NcAppNavigation` sidebar when the surrounding container gets
 * narrower than NAV_COLLAPSE_BREAKPOINT and reopens it when it grows back.
 *
 * Unlike hiding the navigation, this drives the component's built-in open/close
 * (by clicking its own toggle button) so the toggle stays visible and the
 * sidebar remains reachable on a narrow dialog.
 *
 * The consuming component must set `ref="container"` on the observed element
 * and `ref="appNav"` on its `<NcAppNavigation>`. If the container is rendered
 * conditionally, call `setupNavObserver()` again once it appears.
 */
export default {
	mounted() {
		this.setupNavObserver()
	},
	beforeUnmount() {
		this.teardownNavObserver()
	},
	methods: {
		setupNavObserver() {
			this.teardownNavObserver()
			const container = this.$refs.container
			if (!container || typeof ResizeObserver === 'undefined') {
				return
			}
			// null = not evaluated yet, so the first observation always applies
			this.navCollapsed = null
			this.navResizeObserver = new ResizeObserver(this.onNavContainerResize)
			this.navResizeObserver.observe(container)
		},
		teardownNavObserver() {
			this.navResizeObserver?.disconnect()
			this.navResizeObserver = null
		},
		onNavContainerResize(entries) {
			const width = entries[0]?.contentRect.width
			if (width === undefined) {
				return
			}
			const shouldCollapse = width < NAV_COLLAPSE_BREAKPOINT
			if (shouldCollapse === this.navCollapsed) {
				return
			}
			this.navCollapsed = shouldCollapse
			// defer to the next frame to avoid "ResizeObserver loop" warnings
			window.requestAnimationFrame(() => this.setNavOpen(!shouldCollapse))
		},
		setNavOpen(open) {
			// We deliberately drive NcAppNavigation through its own toggle button
			// rather than emit('toggle-navigation') from @nextcloud/event-bus: that
			// bus is global, so it would also collapse the host app's sidebar when
			// resizing the assistant modal. NcAppNavigation exposes no `open` prop
			// and doesn't expose toggleNavigation(), so its toggle button is the
			// only instance-scoped lever.
			const toggle = this.$refs.appNav?.$el?.querySelector('button.app-navigation-toggle')
			if (!toggle) {
				return
			}
			const isOpen = toggle.getAttribute('aria-expanded') === 'true'
			if (isOpen !== open) {
				toggle.click()
			}
		},
	},
}
