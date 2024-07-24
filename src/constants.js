export const TASK_STATUS_INT = {
	cancelled: 5,
	failed: 4,
	successful: 3,
	running: 2,
	scheduled: 1,
	unknown: 0,
}

export const TASK_STATUS_STRING = {
	cancelled: 'STATUS_CANCELLED',
	failed: 'STATUS_FAILED',
	successful: 'STATUS_SUCCESSFUL',
	running: 'STATUS_RUNNING',
	scheduled: 'STATUS_SCHEDULED',
	unknown: 'STATUS_UNKNOWN',
}

export const TASK_CATEGORIES = {
	text_generation: 0,
	image_generation: 1,
	speech_to_text: 2,
}

export const SHAPE_TYPES = {
	Number: 0,
	Text: 1,
	Image: 2,
	Audio: 3,
	Video: 4,
	File: 5,
	ListOfNumbers: 10,
	ListOfTexts: 11,
	ListOfImages: 12,
	ListOfAudios: 13,
	ListOfVideos: 14,
	ListOfFiles: 15,
}

export const SHAPE_TYPE_NAMES = {
	Number: 'Number',
	Text: 'Text',
	Image: 'Image',
	Audio: 'Audio',
	Video: 'Video',
	File: 'File',
	Enum: 'Enum',
	ListOfNumbers: 'ListOfNumbers',
	ListOfTexts: 'ListOfTexts',
	ListOfImages: 'ListOfImages',
	ListOfAudios: 'ListOfAudios',
	ListOfVideos: 'ListOfVideos',
	ListOfFiles: 'ListOfFiles',
}

export const VALID_AUDIO_MIME_TYPES = [
	'audio/mpeg',
	'audio/mp4',
	'audio/ogg',
	'audio/wav',
	'audio/x-wav',
	'audio/webm',
	'audio/opus',
	'audio/flac',
	'audio/vorbis',
	'audio/m4b',
]

export const VALID_IMAGE_MIME_TYPES = [
	'image/*',
]

export const VALID_VIDEO_MIME_TYPES = [
	'video/*',
]
