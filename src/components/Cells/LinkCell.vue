			const types = allowedTypes.flatMap(type => {
				switch (type) {
				case 'File':
					return ['file']
				case 'Talk':
					return ['talk']
				case 'Contact':
					return ['contact', 'user']
				case 'User':
					return ['user']
				case 'Group':
					return ['group']
				case 'Circle':
					return ['circle']
				case 'Deck':
					return ['deck']
				case 'Calendar':
					return ['calendar']
				case 'Mail':
					return ['mail']
				default:
					return []
				}
			})
			return [...new Set(types)]
