<?php
namespace ContentForge\Generator;

use WP_Error;
use ContentForge\Activator;

global $wpdb;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Generator for fake posts.
 */
class Post extends Generator
{
	/**
	 * Generate fake posts.
	 *
	 * @param int $count
	 * @param array $args
	 * @return array Array of generated post IDs.
	 */
	public function generate($count = 1, $args = [])
	{
		$ids = [];
		for ($i = 0; $i < $count; $i++) {
			$post_data = [
				'post_title' => $this->randomize_title(),
				'post_content' => $this->randomize_content(),
				'post_status' => 'publish',
				'post_type' => 'post',
				'post_author' => $this->user_id,
			];

			if (!empty($args)) {
				$post_data = array_merge($post_data, $args);
			}

			$post_id = wp_insert_post($post_data);

			if (!is_wp_error($post_id) && $post_id) {
				$ids[] = $post_id;
				$this->track_generated($post_id);
			}
		}

		return $ids;
	}

	/**
	 * Generate a random meaningful title for posts.
	 *
	 * @return string Generated title
	 */
	private function randomize_title()
	{
		$adjectives = [
			'Amazing',
			'Incredible',
			'Essential',
			'Ultimate',
			'Hidden',
			'Simple',
			'Creative',
			'Powerful',
			'Effective',
			'Practical',
			'Revolutionary',
			'Innovative',
			'Comprehensive',
			'Advanced',
			'Expert',
			'Professional',
			'Modern',
			'Complete',
			'Perfect',
			'Outstanding',
			'Exceptional',
			'Remarkable',
			'Brilliant',
			'Stunning',
			'Fantastic',
			'Extraordinary',
			'Magnificent',
			'Impressive',
			'Unique',
			'Exclusive',
			'Premium',
			'Superior',
			'Excellent',
			'Proven',
			'Reliable',
			'Trusted',
			'Authentic',
			'Genuine',
			'Original',
			'Fresh',
			'New',
			'Latest',
			'Cutting-edge',
			'State-of-the-art',
			'Top-notch'
		];

		$nouns = [
			'Guide',
			'Tips',
			'Secrets',
			'Ideas',
			'Tricks',
			'Strategies',
			'Ways',
			'Steps',
			'Lessons',
			'Facts',
			'Methods',
			'Techniques',
			'Approaches',
			'Solutions',
			'Insights',
			'Principles',
			'Concepts',
			'Tools',
			'Resources',
			'Hacks',
			'Shortcuts',
			'Formulas',
			'Blueprints',
			'Templates',
			'Frameworks',
			'Systems',
			'Processes',
			'Procedures',
			'Practices',
			'Tactics',
			'Plans',
			'Schemes',
			'Patterns',
			'Models',
			'Examples',
			'Cases',
			'Studies',
			'Reviews',
			'Analysis',
			'Research',
			'Findings',
			'Discoveries',
			'Revelations',
			'Breakthroughs',
			'Innovations',
			'Trends',
			'Updates',
			'News',
			'Reports',
			'Stories'
		];

		$verbs = [
			'Boost',
			'Improve',
			'Master',
			'Learn',
			'Discover',
			'Understand',
			'Create',
			'Build',
			'Explore',
			'Optimize',
			'Enhance',
			'Develop',
			'Achieve',
			'Unlock',
			'Transform',
			'Revolutionize',
			'Maximize',
			'Accelerate',
			'Streamline',
			'Simplify',
			'Automate',
			'Integrate',
			'Implement',
			'Execute',
			'Deliver',
			'Generate',
			'Produce',
			'Design',
			'Craft',
			'Engineer',
			'Construct',
			'Establish',
			'Launch',
			'Scale',
			'Grow',
			'Expand',
			'Increase',
			'Multiply',
			'Amplify',
			'Strengthen',
			'Fortify',
			'Secure',
			'Protect',
			'Maintain',
			'Sustain',
			'Preserve',
			'Restore',
			'Repair',
			'Fix',
			'Solve',
			'Resolve',
			'Overcome',
			'Conquer'
		];

		$topics = [
			'Productivity',
			'Marketing',
			'Writing',
			'Design',
			'Success',
			'Technology',
			'Health',
			'Finance',
			'Coding',
			'Growth',
			'Business',
			'Leadership',
			'Management',
			'Innovation',
			'Creativity',
			'Communication',
			'Networking',
			'Sales',
			'Customer Service',
			'Branding',
			'Strategy',
			'Planning',
			'Organization',
			'Efficiency',
			'Performance',
			'Quality',
			'Excellence',
			'Improvement',
			'Development',
			'Training',
			'Education',
			'Learning',
			'Skills',
			'Knowledge',
			'Expertise',
			'Experience',
			'Wisdom',
			'Intelligence',
			'Analytics',
			'Data',
			'Research',
			'Science',
			'Engineering',
			'Architecture',
			'Construction',
			'Manufacturing',
			'Operations',
			'Logistics',
			'Supply Chain',
			'E-commerce',
			'Digital Marketing',
			'Social Media',
			'Content Creation',
			'SEO',
			'Web Development'
		];

		$industries = [
			'Healthcare',
			'Education',
			'Real Estate',
			'Automotive',
			'Fashion',
			'Food',
			'Travel',
			'Entertainment',
			'Sports',
			'Gaming',
			'Music',
			'Art',
			'Photography',
			'Film',
			'Publishing',
			'Media',
			'Journalism',
			'Consulting',
			'Legal',
			'Accounting',
			'Insurance',
			'Banking',
			'Investment',
			'Retail',
			'Hospitality',
			'Construction',
			'Agriculture',
			'Energy',
			'Environment',
			'Sustainability',
			'Non-profit',
			'Government'
		];

		$structures = [
			// Basic structures
			['verb', 'your', 'adjective', 'noun'], // e.g., Boost Your Creative Ideas
			['the', 'adjective', 'noun', 'for', 'topic'], // The Ultimate Guide for Marketing
			['how', 'to', 'verb', 'your', 'noun'], // How to Improve Your Skills
			['x', 'adjective', 'nouns', 'to', 'verb'], // 10 Simple Tricks to Master
			['why', 'topic', 'needs', 'adjective', 'nouns'], // Why Marketing Needs Creative Ideas
			['noun', 'that', 'verb', 'topic'], // Tips That Boost Productivity

			// Advanced structures
			['the', 'adjective', 'noun', 'to', 'verb', 'topic'], // The Complete Guide to Master Marketing
			['verb', 'adjective', 'topic', 'with', 'these', 'nouns'], // Master Digital Marketing with These Strategies
			['x', 'ways', 'to', 'verb', 'your', 'topic', 'noun'], // 7 Ways to Boost Your Business Growth
			['adjective', 'noun', 'for', 'topic', 'in', 'industry'], // Essential Tips for Marketing in Healthcare
			['how', 'adjective', 'topic', 'can', 'verb', 'your', 'noun'], // How Modern Technology Can Improve Your Productivity
			['the', 'noun', 'every', 'topic', 'professional', 'should', 'know'], // The Secrets Every Marketing Professional Should Know
			['from', 'adjective', 'to', 'adjective', 'a', 'topic', 'noun'], // From Simple to Advanced: A Marketing Guide
			['verb', 'like', 'a', 'adjective', 'topic', 'expert'], // Design Like a Professional Marketing Expert
			['the', 'adjective', 'topic', 'noun', 'you', 'need'], // The Complete Marketing Strategy You Need
			['adjective', 'noun', 'that', 'will', 'verb', 'your', 'topic'] // Powerful Techniques That Will Transform Your Business
		];

		// Randomly pick a structure
		$template = $structures[array_rand($structures)];

		// Replace keywords with random words
		$title = array_map(function ($word) use ($adjectives, $nouns, $verbs, $topics, $industries) {
			switch ($word) {
				case 'adjective':
					return $adjectives[array_rand($adjectives)];
				case 'noun':
					return $nouns[array_rand($nouns)];
				case 'nouns':
					return $nouns[array_rand($nouns)];
				case 'verb':
					return $verbs[array_rand($verbs)];
				case 'topic':
					return $topics[array_rand($topics)];
				case 'industry':
					return $industries[array_rand($industries)];
				case 'x':
					return rand(3, 15); // Number
				case 'ways':
					return rand(2, 4) === 2 ? 'Ways' : 'Methods';
				case 'professional':
					return rand(2, 4) === 2 ? 'Professional' : 'Expert';
				default:
					return $word;
			}
		}, $template);

		// Capitalize the first letter of the sentence and join
		$title_str = ucfirst(implode(' ', $title));

		return $title_str;
	}

	/**
	 * Generate random content for posts.
	 *
	 * @return string Generated content
	 */
	private function randomize_content()
	{
		$sentences = [
			'In today\'s fast-paced digital world, businesses need to stay ahead of the competition by implementing innovative strategies.',
			'The key to success lies in understanding your target audience and delivering value that exceeds their expectations.',
			'Technology continues to evolve at an unprecedented rate, creating new opportunities for growth and development.',
			'Effective communication is the foundation of any successful organization, fostering collaboration and driving results.',
			'Data-driven decision making has become essential for companies looking to optimize their operations and maximize ROI.',
			'Customer experience has emerged as a critical differentiator in today\'s competitive marketplace.',
			'Automation and artificial intelligence are transforming industries and reshaping the future of work.',
			'Sustainable practices are no longer optional but necessary for long-term business viability.',
			'Remote work has fundamentally changed how teams collaborate and organizations operate.',
			'Innovation requires a culture that encourages experimentation and embraces calculated risks.',
			'Digital transformation is not just about technology; it\'s about reimagining business processes.',
			'Leadership in the modern era demands adaptability, empathy, and strategic vision.',
			'Content marketing has proven to be one of the most effective ways to build brand awareness.',
			'Social media platforms continue to evolve, offering new ways to connect with audiences.',
			'E-commerce has revolutionized retail, creating opportunities for businesses of all sizes.',
			'Cybersecurity has become a top priority as organizations face increasingly sophisticated threats.',
			'The gig economy has created new employment models and changed traditional career paths.',
			'Personalization at scale is now possible thanks to advanced analytics and machine learning.',
			'Agile methodologies have transformed project management and software development practices.',
			'Environmental consciousness is driving innovation in product design and manufacturing.',
			'Mobile-first design has become essential as smartphone usage continues to dominate.',
			'Cloud computing has democratized access to enterprise-level technology and infrastructure.',
			'User experience design plays a crucial role in product adoption and customer satisfaction.',
			'Blockchain technology promises to revolutionize various industries beyond cryptocurrency.',
			'Artificial intelligence is augmenting human capabilities rather than replacing them entirely.',
			'Cross-functional collaboration is essential for delivering complex projects successfully.',
			'Continuous learning has become a necessity in rapidly changing professional landscapes.',
			'Brand authenticity resonates more strongly with consumers than traditional advertising.',
			'Omnichannel strategies ensure consistent customer experiences across all touchpoints.',
			'Performance metrics and KPIs provide valuable insights for strategic decision making.',
			'Diversity and inclusion initiatives drive innovation and improve organizational performance.',
			'Scalability considerations must be built into systems and processes from the beginning.',
			'Customer feedback loops enable continuous improvement and product refinement.',
			'Market research provides the foundation for informed business strategy development.',
			'Quality assurance processes ensure products meet or exceed customer expectations.',
			'Competitive analysis helps organizations identify opportunities and threats in their market.',
			'Resource optimization maximizes efficiency while minimizing waste and costs.',
			'Strategic partnerships can accelerate growth and expand market reach.',
			'Change management is critical for successful organizational transformation initiatives.',
			'Risk assessment and mitigation strategies protect businesses from potential threats.'
		];

		$paragraphs = rand(2, 5);
		$content = '';

		for ($i = 0; $i < $paragraphs; $i++) {
			$sentences_per_paragraph = rand(3, 6);
			$paragraph = '<p>';

			for ($j = 0; $j < $sentences_per_paragraph; $j++) {
				$paragraph .= $sentences[array_rand($sentences)] . ' ';
			}

			$paragraph = trim($paragraph) . '</p>';
			$content .= $paragraph;

			if ($i < $paragraphs - 1) {
				$content .= "\n\n";
			}
		}

		// Append the Content Forge attribution
		$content .= "\n\n<p><em>This is a fake post generated by Content Forge.</em></p>";

		return $content;
	}

	/**
	 * Delete generated posts by IDs.
	 *
	 * @param array $object_ids
	 * @return int Number of items deleted.
	 */
	public function delete(array $object_ids)
	{
		$deleted = 0;
		foreach ($object_ids as $post_id) {
			if (wp_delete_post($post_id, true)) {
				$deleted++;
				$this->untrack_generated($post_id);
			}
		}
		return $deleted;
	}

	/**
	 * Track generated post in the custom DB table.
	 *
	 * @param int $post_id
	 */
	protected function track_generated($post_id)
	{
		global $wpdb;

		// We use direct DB access here because we are tracking generated posts in a custom table,
		// and there is no WordPress API for this use case. All data is sanitized and prepared.
		Activator::create_tracking_table();

		$post = get_post($post_id);
		$data_type = $post ? $post->post_type : 'post';
		$table = $wpdb->prefix . CFORGE_DBNAME;
		$object_id = intval($post_id);
		$data_type = sanitize_key($data_type);
		$created_at = current_time('mysql');
		$created_by = intval($this->user_id);

		$result = $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO $table (object_id, data_type, created_at, created_by) VALUES (%d, %s, %s, %d)",
				$object_id,
				$data_type,
				$created_at,
				$created_by
			)
		);

		if ($result === false) {
			// Optionally log or handle the error
			error_log('Failed to insert generated post tracking record for post_id: ' . $object_id);
		}
	}

	/**
	 * Remove tracking info for a deleted post.
	 *
	 * @param int $post_id
	 */
	protected function untrack_generated($post_id)
	{
		global $wpdb;

		$wpdb->delete(
			$wpdb->prefix . CFORGE_DBNAME,
			[
				'object_id' => $post_id,
			]
		);
	}
}