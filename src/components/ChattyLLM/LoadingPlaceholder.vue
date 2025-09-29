<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<ul :class="'placeholder-list placeholder-list--' + type">
		<li v-for="(item, index) in placeholderData" :key="index" class="placeholder-item">
			<div class="placeholder-item__avatar" :style="{ '--avatar-size': item.avatarSize }">
				<div class="placeholder-item__avatar-circle" />
			</div>
			<div class="placeholder-item__content" :style="{'--last-line-width': item.width}">
				<div v-for="idx in item.amount" :key="idx" class="placeholder-item__content-line" />
			</div>
			<div v-if="type === 'messages'" class="placeholder-item__info" />
		</li>
		<NcNoteCard
			v-if="slowPickup"
			type="warning">
			{{ t('assistant', 'This chat response is taking longer to start generating than expected. Please contact your administrator to ensure that Assistant is correctly configured.') }}
		</NcNoteCard>
	</ul>
</template>

<script>
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'

const AVATAR = {
	SIZE: {
		EXTRA_SMALL: 22,
		COMPACT: 24,
		SMALL: 32,
		DEFAULT: 40,
		MEDIUM: 64,
		LARGE: 128,
		EXTRA_LARGE: 180,
		FULL: 512,
	},
}

export default {
	name: 'LoadingPlaceholder',

	components: {
		NcNoteCard,
	},

	props: {
		type: {
			type: String,
			default: 'messages',
			validator(value) {
				return ['conversations', 'messages', 'participants'].includes(value)
			},
		},
		count: {
			type: Number,
			default: 1,
		},
		slowPickup: {
			type: Boolean,
			default: false,
		},
	},

	computed: {
		placeholderData() {
			const data = []
			for (let i = 0; i < this.count; i++) {
				// set up amount of lines in skeleton and generate random widths for last line
				data.push({
					amount: this.type === 'messages' ? 4 : this.type === 'conversations' ? 2 : 1,
					width: this.type === 'participants' ? '60%' : (Math.floor(Math.random() * 40) + 30) + '%',
					avatarSize: (this.type === 'messages' ? AVATAR.SIZE.SMALL : AVATAR.SIZE.DEFAULT) + 'px',
				})
			}
			return data
		},
	},
}
</script>

<style lang="scss" scoped>
.placeholder-list {
	width: 100%;
	transform: translateZ(0); // enable hardware acceleration
}

.placeholder-item {
	display: flex;
	gap: 8px;
	width: 100%;

	&__avatar {
		flex-shrink: 0;
		&-circle {
			height: var(--avatar-size);
			width: var(--avatar-size);
			border-radius: var(--avatar-size);
		}
	}

	&__content {
		display: flex;
		flex-direction: column;
		width: 100%;

		&-line {
			margin: 5px 0 4px;
			width: 100%;
			height: 15px;

			&:last-child {
				width: var(--last-line-width);
			}
		}
	}
}

// Conversations placeholder ruleset
.placeholder-list--conversations {
	.placeholder-item {
		margin: 2px 0;
		padding: 8px 10px;

		&__content {
			width: 70%;
		}
	}
}

// Messages placeholder ruleset
.placeholder-list--messages {
	max-width: 100%;
	margin: auto;

	.placeholder-item {
		padding-inline-end: 8px;
		&__avatar {
			padding: 8px 0 0 8px;
		}

		&__content {
			max-width: 100%;
			padding: 12px 0;

			&-line {
				margin: 4px 0 3px;

				&:first-child {
					margin-bottom: 9px;
					width: 20%;
				}
			}
		}

		&__info {
			width: 100px;
			height: 15px;
			margin-block: 16px 0;
			margin-inline: 8px;
			animation-delay: 0.8s;
		}
	}
}

// Participants placeholder ruleset
.placeholder-list--participants {
	.placeholder-item {
		--padding : calc(var(--default-grid-baseline) * 2);
		gap: calc(var(--default-grid-baseline) * 2);
		padding: calc(var(--padding) * 3 / 2) var(--padding) var(--padding);
		height: 59px;
		align-items: center;

		&__avatar {
			margin: auto;
		}
	}
}

// Animation
.placeholder-item__avatar-circle,
.placeholder-item__content-line,
.placeholder-item__info {
	background-size: 200vw;
	background-image: linear-gradient(90deg, var(--color-placeholder-dark) 65%, var(--color-placeholder-light) 70%, var(--color-placeholder-dark) 75%);
	animation: loading-animation 3s forwards infinite linear;
	will-change: background-position;
}

/* No animation to avoid vestibular motion triggers. */
@media (prefers-reduced-motion: reduce) {
  .placeholder-item__avatar-circle,
  .placeholder-item__content-line,
  .placeholder-item__info {
	animation: none;
	}
}

@keyframes loading-animation {
	0% {
		background-position: 0;
	}
	100% {
		background-position: 140vw;
	}
}
</style>
