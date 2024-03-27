<?php

namespace OCA\Assistant\Settings;

use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Settings\IIconSection;

class PersonalSection implements IIconSection {

	public function __construct(
		private IURLGenerator $urlGenerator,
		private IL10N $l
	) {
	}

	/**
	 * returns the ID of the section. It is supposed to be a lower case string
	 *
	 * @returns string
	 */
	public function getID(): string {
		return 'ai';
	}

	/**
	 * returns the translated name as it should be displayed, e.g. 'LDAP / AD
	 * integration'. Use the L10N service to translate it.
	 *
	 * @return string
	 */
	public function getName(): string {
		return $this->l->t('Artificial Intelligence');
	}

	/**
	 * @return int whether the form should be rather on the top or bottom of
	 * the settings navigation. The sections are arranged in ascending order of
	 * the priority values. It is required to return a value between 0 and 99.
	 */
	public function getPriority(): int {
		return 40;
	}

	/**
	 * @return string The relative path to an icon describing the section
	 */
	public function getIcon(): string {
		return $this->urlGenerator->imagePath('settings', 'ai.svg');
	}

}
