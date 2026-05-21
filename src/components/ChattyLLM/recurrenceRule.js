/*
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { getDayNames, getMonthNames, translatePlural as n, translate as t } from '@nextcloud/l10n'
import moment from '@nextcloud/moment'

/**
 * Formats a recurrence-rule
 *
 * @param {object} recurrenceRule The recurrence-rule to format
 * @param {string} locale The locale to format it into
 * @return {string}
 */
export default (recurrenceRule, locale) => {
	if (recurrenceRule.frequency === 'NONE') {
		return t('assistant', 'Does not repeat')
	}

	let freqPart = ''
	if (recurrenceRule.interval === 1) {
		switch (recurrenceRule.frequency) {
		case 'MINUTELY':
			freqPart = t('assistant', 'Minutely')
			break

		case 'HOURLY':
			freqPart = t('assistant', 'Hourly')
			break

		case 'DAILY':
			freqPart = t('assistant', 'Daily')
			break

		case 'WEEKLY':
			freqPart = t('assistant', 'Weekly')
			break

		case 'MONTHLY':
			freqPart = t('assistant', 'Monthly')
			break

		case 'YEARLY':
			freqPart = t('assistant', 'Yearly')
			break
		}
	} else {
		switch (recurrenceRule.frequency) {
		case 'MINUTELY':
			freqPart = n('assistant', 'Every %n minute', 'Every %n minutes', recurrenceRule.interval)
			break

		case 'HOURLY':
			freqPart = n('assistant', 'Every %n hour', 'Every %n hours', recurrenceRule.interval)
			break

		case 'DAILY':
			freqPart = n('assistant', 'Every %n day', 'Every %n days', recurrenceRule.interval)
			break

		case 'WEEKLY':
			freqPart = n('assistant', 'Every %n week', 'Every %n weeks', recurrenceRule.interval)
			break

		case 'MONTHLY':
			freqPart = n('assistant', 'Every %n month', 'Every %n months', recurrenceRule.interval)
			break

		case 'YEARLY':
			freqPart = n('assistant', 'Every %n year', 'Every %n years', recurrenceRule.interval)
			break
		}
	}

	let limitPart = ''
	if (recurrenceRule.frequency === 'WEEKLY' && recurrenceRule.byDay.length !== 0) {
		const formattedDays = getTranslatedByDaySet(recurrenceRule.byDay)

		limitPart = n('assistant', 'on {weekday}', 'on {weekdays}', recurrenceRule.byDay.length, {
			weekday: formattedDays,
			weekdays: formattedDays,
		})
	} else if (recurrenceRule.frequency === 'MONTHLY') {
		if (recurrenceRule.byMonthDay.length !== 0) {
			const dayOfMonthList = recurrenceRule.byMonthDay.join(', ')

			limitPart = n('assistant', 'on day {dayOfMonthList}', 'on days {dayOfMonthList}', recurrenceRule.byMonthDay.length, {
				dayOfMonthList,
			})
		} else {
			const ordinalNumber = getTranslatedOrdinalNumber(recurrenceRule.bySetPosition)
			const byDaySet = getTranslatedByDaySet(recurrenceRule.byDay)

			limitPart = t('assistant', 'on the {ordinalNumber} {byDaySet}', {
				ordinalNumber,
				byDaySet,
			})
		}
	} else if (recurrenceRule.frequency === 'YEARLY') {
		const monthNames = getTranslatedMonths(recurrenceRule.byMonth)

		if (recurrenceRule.byMonthDay.length !== 0) {
			const dayOfMonthList = recurrenceRule.byMonthDay.join(', ')

			limitPart = t('assistant', 'in {monthNames} on the {dayOfMonthList}', {
				monthNames,
				dayOfMonthList,
			})
		} else {
			const ordinalNumber = getTranslatedOrdinalNumber(recurrenceRule.bySetPosition)
			const byDaySet = getTranslatedByDaySet(recurrenceRule.byDay)

			// Prevent empty text in those sections
			if (ordinalNumber !== '' && byDaySet !== '') {
				limitPart = t('assistant', 'in {monthNames} on the {ordinalNumber} {byDaySet}', {
					monthNames,
					ordinalNumber,
					byDaySet,
				})
			}
		}
	}

	let endPart = ''
	if (recurrenceRule.until !== null) {
		const untilDate = moment(recurrenceRule.until).locale(locale).format('L')

		endPart = t('assistant', 'until {untilDate}', {
			untilDate,
		})
	} else if (recurrenceRule.count !== null) {
		endPart = n('assistant', '%n time', '%n times', recurrenceRule.count)
	}

	return [
		freqPart,
		limitPart,
		endPart,
	].join(' ').replace(/\s{2,}/g, ' ').trim()
}

/**
 * Gets the byDay list as formatted list of translated weekdays
 *
 * @param {string[]} byDayList The by-day-list to get formatted
 * @return {string}
 */
function getTranslatedByDaySet(byDayList) {
	const byDayNames = []
	const allByDayNames = getDayNames()

	// TODO: This should be sorted by first day of week
	// TODO: This should summarise:
	//  - SA, SU to weekend
	//  - MO, TU, WE, TH, FR to weekday
	//  - MO, TU, WE, TH, FR, SA, SU to day

	if (byDayList.includes('MO')) {
		byDayNames.push(allByDayNames[1])
	}
	if (byDayList.includes('TU')) {
		byDayNames.push(allByDayNames[2])
	}
	if (byDayList.includes('WE')) {
		byDayNames.push(allByDayNames[3])
	}
	if (byDayList.includes('TH')) {
		byDayNames.push(allByDayNames[4])
	}
	if (byDayList.includes('FR')) {
		byDayNames.push(allByDayNames[5])
	}
	if (byDayList.includes('SA')) {
		byDayNames.push(allByDayNames[6])
	}
	if (byDayList.includes('SU')) {
		byDayNames.push(allByDayNames[0])
	}

	return byDayNames.join(', ')
}

/**
 * Gets the byMonth list as formatted list of translated month-names
 *
 * @param {string[]} byMonthList The by-month list to get formatted
 * @return {string}
 */
function getTranslatedMonths(byMonthList) {
	const sortedByMonth = byMonthList.slice().map((n) => parseInt(n, 10))
	sortedByMonth.sort((a, b) => a - b)

	const monthNames = []
	const allMonthNames = getMonthNames()

	for (const month of sortedByMonth) {
		monthNames.push(allMonthNames[month - 1])
	}

	return monthNames.join(', ')
}

/**
 * Gets the translated ordinal number for by-set-position
 *
 * @param {number} bySetPositionNum The by-set-position number to get the translation of
 * @return {string}
 */
export function getTranslatedOrdinalNumber(bySetPositionNum) {
	switch (bySetPositionNum) {
	case 1:
		return t('assistant', 'first')

	case 2:
		// TRANSLATORS This refers to the second item in a series, not to the unit of time
		return t('assistant', 'second')

	case 3:
		return t('assistant', 'third')

	case 4:
		return t('assistant', 'fourth')

	case 5:
		return t('assistant', 'fifth')

	case -2:
		return t('assistant', 'second to last')

	case -1:
		return t('assistant', 'last')

	default:
		return ''
	}
}
