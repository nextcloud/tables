/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { translate as t } from '@nextcloud/l10n'

/**
 * Translatable names for the icons in img/material/meta.json, describing
 * what each pictogram shows. Each name is a literal t() call so the
 * translation extractor picks it up.
 */
const iconNames = {
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	account: t('tables', 'Person'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	adjust: t('tables', 'Target circle'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	alarm: t('tables', 'Alarm clock'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	album: t('tables', 'Vinyl record'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	alert: t('tables', 'Warning triangle'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	anchor: t('tables', 'Anchor'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	android: t('tables', 'Android logo'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	animation: t('tables', 'Stacked frames'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	antenna: t('tables', 'Antenna'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	api: t('tables', 'API symbol'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	apps: t('tables', 'App grid'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	assistant: t('tables', 'Speech bubble with sparkle'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	at: t('tables', 'At sign'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	atm: t('tables', 'ATM'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	attachment: t('tables', 'Horizontal paperclip'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	atv: t('tables', 'Quad bike'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	autorenew: t('tables', 'Clockwise circular arrows'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	baby: t('tables', 'Baby'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	backspace: t('tables', 'Backspace key'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	balcony: t('tables', 'Balcony'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	ballot: t('tables', 'Ballot paper'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	bandage: t('tables', 'Bandage'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	bank: t('tables', 'Bank building'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	barrel: t('tables', 'Oil barrel'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	basket: t('tables', 'Shopping basket'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	basketball: t('tables', 'Basketball'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	bathtub: t('tables', 'Bathtub'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	battery: t('tables', 'Battery'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	bed: t('tables', 'Bed'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	bee: t('tables', 'Bee'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	bell: t('tables', 'Bell'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	biathlon: t('tables', 'Biathlon'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	bike: t('tables', 'Bicycle'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	blender: t('tables', 'Blender'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	blur: t('tables', 'Blur dots'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	book: t('tables', 'Book'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	bookmark: t('tables', 'Bookmark'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	boombox: t('tables', 'Boombox'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	briefcase: t('tables', 'Briefcase'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	broadcast: t('tables', 'Broadcast signal'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	brush: t('tables', 'Brush'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	bug: t('tables', 'Bug'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	bullhorn: t('tables', 'Bullhorn'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	bus: t('tables', 'Bus'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	cached: t('tables', 'Counterclockwise circular arrows'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	cake: t('tables', 'Cake'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	calendar: t('tables', 'Calendar'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	camera: t('tables', 'Camera'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	cancel: t('tables', 'Prohibition sign'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	candle: t('tables', 'Candle'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	car: t('tables', 'Car'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	carabiner: t('tables', 'Carabiner'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	cart: t('tables', 'Shopping cart'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	cast: t('tables', 'Casting screen'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	cellphone: t('tables', 'Mobile phone'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	charity: t('tables', 'Hand holding a heart'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	check: t('tables', 'Check mark'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	checkbook: t('tables', 'Checkbook'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	church: t('tables', 'Church'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	city: t('tables', 'City'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	clock: t('tables', 'Clock'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	close: t('tables', 'Cross mark'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	cloud: t('tables', 'Cloud'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	coffee: t('tables', 'Coffee cup'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	cog: t('tables', 'Gear'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	cogs: t('tables', 'Gears'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	collage: t('tables', 'Collage'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	compare: t('tables', 'Side-by-side panels'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	compass: t('tables', 'Compass'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	compost: t('tables', 'Compost'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	contacts: t('tables', 'Contact card'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	controller: t('tables', 'Game controller'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	cookie: t('tables', 'Cookie'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	copyright: t('tables', 'Copyright symbol'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	countertop: t('tables', 'Kitchen counter'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	cradle: t('tables', 'Cradle'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	creation: t('tables', 'Sparkles'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	cricket: t('tables', 'Cricket'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	crop: t('tables', 'Crop marks'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	crosshairs: t('tables', 'Crosshairs'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	crowd: t('tables', 'Crowd'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	delete: t('tables', 'Trash can'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	delta: t('tables', 'Triangle'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	details: t('tables', 'Inverted triangle'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	devices: t('tables', 'Phone and monitor'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	dialpad: t('tables', 'Keypad'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	directions: t('tables', 'Direction sign'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	dishwasher: t('tables', 'Dishwasher'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	diving: t('tables', 'Diving'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	dns: t('tables', 'DNS servers'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	domain: t('tables', 'Office building'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	download: t('tables', 'Download arrow'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	dumbbell: t('tables', 'Dumbbell'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	earth: t('tables', 'Earth globe'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	eject: t('tables', 'Eject symbol'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	email: t('tables', 'Envelope'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	emoticon: t('tables', 'Smiley face'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	equalizer: t('tables', 'Equalizer bars'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	eye: t('tables', 'Eye'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	fax: t('tables', 'Fax machine'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	fencing: t('tables', 'Fencing'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	ferry: t('tables', 'Ferry'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	file: t('tables', 'Document page'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	film: t('tables', 'Film roll'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	filmstrip: t('tables', 'Filmstrip'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	finance: t('tables', 'Growth chart'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	fingerprint: t('tables', 'Fingerprint'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	fire: t('tables', 'Flame'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	firebase: t('tables', 'Firebase logo'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	flag: t('tables', 'Flag'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	flare: t('tables', 'Lens flare'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	flash: t('tables', 'Lightning bolt'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	flower: t('tables', 'Flower'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	folder: t('tables', 'Folder'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	food: t('tables', 'Burger and drink'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	football: t('tables', 'American football'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	forest: t('tables', 'Forest'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	forum: t('tables', 'Chat bubbles'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	forward: t('tables', 'Forward arrow'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	fullscreen: t('tables', 'Fullscreen corners'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	gamepad: t('tables', 'Directional pad'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	gauge: t('tables', 'Gauge'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	gesture: t('tables', 'Freehand line'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	gmail: t('tables', 'Gmail logo'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	golf: t('tables', 'Golf'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	google: t('tables', 'Google logo'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	grain: t('tables', 'Grain dots'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	grass: t('tables', 'Grass'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	grid: t('tables', 'Grid'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	grill: t('tables', 'Barbecue grill'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	gymnastics: t('tables', 'Gymnastics'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	hail: t('tables', 'Person hailing a taxi'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	hammer: t('tables', 'Hammer'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	handball: t('tables', 'Handball'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	hdr: t('tables', 'HDR label'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	headphones: t('tables', 'Headphones'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	headset: t('tables', 'Headset with microphone'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	heart: t('tables', 'Heart'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	hiking: t('tables', 'Hiking'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	history: t('tables', 'Clock with counterclockwise arrow'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	home: t('tables', 'House'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	hub: t('tables', 'Network hub'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	human: t('tables', 'Human figure'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	hvac: t('tables', 'Air vent'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	image: t('tables', 'Picture'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	inbox: t('tables', 'Inbox tray'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	information: t('tables', 'Information symbol'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	iron: t('tables', 'Clothes iron'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	kabaddi: t('tables', 'Kabaddi'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	karate: t('tables', 'Karate'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	kayaking: t('tables', 'Kayaking'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	key: t('tables', 'Key'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	keyboard: t('tables', 'Keyboard'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	kite: t('tables', 'Kite'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	kitesurfing: t('tables', 'Kitesurfing'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	label: t('tables', 'Label shape'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	ladybug: t('tables', 'Ladybug'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	landslide: t('tables', 'Landslide'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	laptop: t('tables', 'Laptop'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	launch: t('tables', 'External link arrow'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	layers: t('tables', 'Stacked layers'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	leak: t('tables', 'Signal waves'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	library: t('tables', 'Books on a shelf'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	license: t('tables', 'Award seal'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	lightbulb: t('tables', 'Lightbulb'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	link: t('tables', 'Chain link'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	liquor: t('tables', 'Liquor bottles'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	lock: t('tables', 'Padlock'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	login: t('tables', 'Sign-in arrow'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	logout: t('tables', 'Sign-out arrow'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	looks: t('tables', 'Rainbow'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	loupe: t('tables', 'Magnifying glass with plus'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	magnify: t('tables', 'Magnifying glass'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	map: t('tables', 'Map'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	marker: t('tables', 'Highlighter'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	medication: t('tables', 'Pill bottle'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	meditation: t('tables', 'Meditation'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	memory: t('tables', 'Memory chip'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	menu: t('tables', 'Menu lines'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	merge: t('tables', 'Merging arrows'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	message: t('tables', 'Speech bubble'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	microphone: t('tables', 'Microphone'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	minus: t('tables', 'Minus sign'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	monitor: t('tables', 'Computer monitor'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	moped: t('tables', 'Moped'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	more: t('tables', 'More options symbol'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	mosque: t('tables', 'Mosque'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	motion: t('tables', 'Motion lines'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	motorbike: t('tables', 'Motorbike'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	mouse: t('tables', 'Computer mouse'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	movie: t('tables', 'Clapperboard'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	mustache: t('tables', 'Mustache'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	nature: t('tables', 'Tree'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	navigation: t('tables', 'Navigation arrow'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	newspaper: t('tables', 'Newspaper'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	noodles: t('tables', 'Noodles'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	opacity: t('tables', 'Half-filled drop'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	overscan: t('tables', 'Overscan frame'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	palette: t('tables', 'Paint palette'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	panorama: t('tables', 'Panorama photo'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	paperclip: t('tables', 'Paperclip'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	paragliding: t('tables', 'Paragliding'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	parking: t('tables', 'Parking sign'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	pasta: t('tables', 'Pasta'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	pause: t('tables', 'Pause symbol'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	pencil: t('tables', 'Pencil'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	phone: t('tables', 'Telephone'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	piano: t('tables', 'Piano'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	pin: t('tables', 'Pushpin'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	pinwheel: t('tables', 'Pinwheel'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	pizza: t('tables', 'Pizza'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	play: t('tables', 'Play symbol'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	plus: t('tables', 'Plus sign'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	podcast: t('tables', 'Podcast symbol'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	polo: t('tables', 'Polo'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	post: t('tables', 'Blog post page'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	power: t('tables', 'Power symbol'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	printer: t('tables', 'Printer'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	publish: t('tables', 'Arrow up to a bar'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	puzzle: t('tables', 'Puzzle'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	racquetball: t('tables', 'Racquetball'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	radar: t('tables', 'Radar screen'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	radio: t('tables', 'Radio'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	raw: t('tables', 'RAW label'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	redo: t('tables', 'Redo arrow'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	refresh: t('tables', 'Refresh arrow'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	reminder: t('tables', 'String tied on a finger'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	remote: t('tables', 'Remote control'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	rename: t('tables', 'Pencil on a line'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	repeat: t('tables', 'Repeat arrows'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	replay: t('tables', 'Replay arrow'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	reply: t('tables', 'Reply arrow'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	restart: t('tables', 'Restart arrow'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	restore: t('tables', 'Restore arrow'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	rewind: t('tables', 'Rewind symbol'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	ribbon: t('tables', 'Ribbon'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	rocket: t('tables', 'Rocket'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	rodent: t('tables', 'Rodent'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	rowing: t('tables', 'Rowing'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	rss: t('tables', 'RSS feed symbol'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	rugby: t('tables', 'Rugby'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	run: t('tables', 'Running person'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	satellite: t('tables', 'Satellite'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	saxophone: t('tables', 'Saxophone'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	scanner: t('tables', 'Scanner'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	school: t('tables', 'Graduation cap'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	scooter: t('tables', 'Scooter'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	sd: t('tables', 'SD card'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	seat: t('tables', 'Chair'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	security: t('tables', 'Shield'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	segment: t('tables', 'Text segments'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	send: t('tables', 'Paper plane'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	shape: t('tables', 'Shapes'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	share: t('tables', 'Share arrow'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	shopping: t('tables', 'Shopping bag'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	shuffle: t('tables', 'Shuffle arrows'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	sigma: t('tables', 'Sigma symbol'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	silverware: t('tables', 'Fork and knife'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	sim: t('tables', 'SIM card'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	skate: t('tables', 'Ice skate'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	skateboard: t('tables', 'Skateboard'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	skateboarding: t('tables', 'Skateboarding'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	ski: t('tables', 'Skier'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	sledding: t('tables', 'Sledding'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	sleep: t('tables', 'Sleep symbol'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	smoking: t('tables', 'Cigarette'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	snowboard: t('tables', 'Snowboard'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	snowflake: t('tables', 'Snowflake'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	snowmobile: t('tables', 'Snowmobile'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	snowshoeing: t('tables', 'Snowshoeing'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	soccer: t('tables', 'Soccer ball'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	spa: t('tables', 'Lotus flower'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	speaker: t('tables', 'Speaker'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	spellcheck: t('tables', 'Spellcheck mark'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	stadium: t('tables', 'Stadium'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	star: t('tables', 'Star'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	steering: t('tables', 'Steering wheel'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	stop: t('tables', 'Stop symbol'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	store: t('tables', 'Storefront'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	subtitles: t('tables', 'Subtitles box'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	subway: t('tables', 'Subway train'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	surfing: t('tables', 'Surfing'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	synagogue: t('tables', 'Synagogue'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	sync: t('tables', 'Sync arrows'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	tab: t('tables', 'Browser tab'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	tablet: t('tables', 'Tablet'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	tag: t('tables', 'Price tag'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	taxi: t('tables', 'Taxi'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	tea: t('tables', 'Tea'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	television: t('tables', 'Television'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	tennis: t('tables', 'Tennis'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	terrain: t('tables', 'Mountains'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	text: t('tables', 'Text lines'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	texture: t('tables', 'Texture pattern'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	thermostat: t('tables', 'Thermostat'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	ticket: t('tables', 'Ticket'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	timelapse: t('tables', 'Timelapse circle'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	toolbox: t('tables', 'Toolbox'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	train: t('tables', 'Train'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	tram: t('tables', 'Tram'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	transition: t('tables', 'Transition shapes'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	translate: t('tables', 'Translation symbol'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	truck: t('tables', 'Truck'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	tsunami: t('tables', 'Tsunami'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	tune: t('tables', 'Slider controls'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	undo: t('tables', 'Undo arrow'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	update: t('tables', 'Clock with clockwise arrow'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	upload: t('tables', 'Upload arrow'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	usb: t('tables', 'USB symbol'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	vacuum: t('tables', 'Vacuum cleaner'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	vibrate: t('tables', 'Vibrating phone'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	video: t('tables', 'Video camera'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	violin: t('tables', 'Violin'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	voicemail: t('tables', 'Voicemail symbol'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	volcano: t('tables', 'Volcano'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	volleyball: t('tables', 'Volleyball'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	vote: t('tables', 'Ballot box'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	walk: t('tables', 'Walking person'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	wallet: t('tables', 'Wallet'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	wallpaper: t('tables', 'Wallpaper'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	watch: t('tables', 'Wristwatch'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	water: t('tables', 'Water drop'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	watermark: t('tables', 'Watermark'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	web: t('tables', 'Web globe'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	widgets: t('tables', 'Widget blocks'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	wrench: t('tables', 'Wrench'),
	// TRANSLATORS: Name of a pictogram in the icon picker, read out by screen readers
	youtube: t('tables', 'YouTube logo'),
}

/**
 * @param {string} name icon name from meta.json
 * @return {string} translated name, or the raw name for an unknown icon
 */
export function iconLabel(name) {
	return iconNames[name] ?? name
}
