module.exports = function (grunt) {
	"use strict";

	// Project configuration
	grunt.initConfig({
		pkg: grunt.file.readJSON("package.json"),

		addtextdomain: {
			options: {
				textdomain: "reviews-tutor-lms",
			},
			update_all_domains: {
				options: {
					updateDomains: true,
				},
				src: [
					"*.php",
					"**/*.php",
					"!.git/**/*",
					"!bin/**/*",
					"!node_modules/**/*",
					"!tests/**/*",
				],
			},
		},

		wp_readme_to_markdown: {
			your_target: {
				files: {
					"README.md": "readme.txt",
				},
			},
		},

		makepot: {
			target: {
				options: {
					domainPath: "/languages",
					exclude: [".git/*", "bin/*", "node_modules/*", "tests/*"],
					mainFile: "reviews-tutor-lms.php",
					potFilename: "reviews-tutor-lms.pot",
					potHeaders: {
						poedit: true,
						"x-poedit-keywordslist": true,
						"X-Poedit-SearchPath-0": "reviews-tutor-lms.php\n",
						"X-Poedit-SearchPath-1": "includes/class-reviews.php\n",
						"X-Poedit-SearchPath-2": "includes/class-main.php\n",
					},
					type: "wp-plugin",
					updateTimestamp: true,
				},
			},
		},
	});

	grunt.loadNpmTasks("grunt-wp-i18n");
	grunt.loadNpmTasks("grunt-wp-readme-to-markdown");
	grunt.registerTask("default", ["i18n", "readme"]);
	grunt.registerTask("i18n", ["addtextdomain", "makepot"]);
	grunt.registerTask("readme", ["wp_readme_to_markdown"]);

	grunt.util.linefeed = "\n";
};
