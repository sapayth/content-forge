<?php
/**
 * Post generator class for Content Forge plugin.
 *
 * @since   1.0.0
 * @package ContentForge
 */

namespace ContentForge\Generator;

use WP_Error;
use ContentForge\Activator;

global $wpdb;
if ( !defined( 'ABSPATH' ) )
{
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
     * @param int   $count Number of posts to generate.
     * @param array $args  Arguments array for post generation.
     *
     * @return array Array of generated post IDs.
     */
    public function generate( $count = 1, $args = [] )
    {
        $ids = [];
        for ( $i = 0; $i < $count; $i++ )
        {
            $post_data = [
                'post_title'   => $this->randomize_title(),
                'post_content' => $this->randomize_content(),
                'post_status'  => 'publish',
                'post_type'    => 'post',
                'post_author'  => $this->user_id,
            ];
            if ( !empty( $args ) )
            {
                $post_data = array_merge( $post_data, $args );
            }
            $post_id = wp_insert_post( $post_data );
            if ( !is_wp_error( $post_id ) && $post_id )
            {
                $ids[] = $post_id;
                $this->track_generated( $post_id );
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
        $adjectives          = [
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
            'Top-notch',
        ];
        $nouns               = [
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
            'Stories',
        ];
        $verbs               = [
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
            'Conquer',
        ];
        $topics              = [
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
            'Web Development',
        ];
        $industries          = [
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
            'Government',
        ];
        $time_modifiers      = [
            '2025',
            'This Year',
            'Next Year',
            'Today',
            'in 2025',
            'Right Now',
            'This Month',
            'This Week',
        ];
        $intensity_modifiers = [
            'Proven',
            'Data-Driven',
            'Science-Backed',
            'Quick',
            'Fast',
            'Instant',
            'Beginner-Friendly',
            'Expert-Level',
            'Battle-Tested',
            'Time-Tested',
            'Actionable',
            'Practical',
        ];
        $structures          = [
            // Basic structures
            [ 'verb', 'your', 'adjective', 'noun' ],
            // e.g., Boost Your Creative Ideas
            [ 'the', 'adjective', 'noun', 'for', 'topic' ],
            // The Ultimate Guide for Marketing
            [ 'how', 'to', 'verb', 'your', 'noun' ],
            // How to Improve Your Skills
            [ 'x', 'adjective', 'nouns', 'to', 'verb' ],
            // 10 Simple Tricks to Master
            [ 'why', 'topic', 'needs', 'adjective', 'nouns' ],
            // Why Marketing Needs Creative Ideas
            [ 'noun', 'that', 'verb', 'topic' ],
            // Tips That Boost Productivity
            // Advanced structures
            [ 'the', 'adjective', 'noun', 'to', 'verb', 'topic' ],
            // The Complete Guide to Master Marketing
            [ 'verb', 'adjective', 'topic', 'with', 'these', 'nouns' ],
            // Master Digital Marketing with These Strategies
            [ 'x', 'ways', 'to', 'verb', 'your', 'topic', 'noun' ],
            // 7 Ways to Boost Your Business Growth
            [ 'adjective', 'noun', 'for', 'topic', 'in', 'industry' ],
            // Essential Tips for Marketing in Healthcare
            [ 'how', 'adjective', 'topic', 'can', 'verb', 'your', 'noun' ],
            // How Modern Technology Can Improve Your Productivity
            [ 'the', 'noun', 'every', 'topic', 'professional', 'should', 'know' ],
            // The Secrets Every Marketing Professional Should Know
            [ 'from', 'adjective', 'to', 'adjective', 'a', 'topic', 'noun' ],
            // From Simple to Advanced: A Marketing Guide
            [ 'verb', 'like', 'a', 'adjective', 'topic', 'expert' ],
            // Design Like a Professional Marketing Expert
            [ 'the', 'adjective', 'topic', 'noun', 'you', 'need' ],
            // The Complete Marketing Strategy You Need
            [ 'adjective', 'noun', 'that', 'will', 'verb', 'your', 'topic' ],
            // Powerful Techniques That Will Transform Your Business
            // New question-based structures
            [ 'why', 'does', 'topic', 'matter', 'for', 'industry' ],
            // Why Does Marketing Matter for Healthcare?
            [ 'what', 'is', 'adjective', 'topic', 'and', 'why', 'you', 'need', 'it' ],
            // What is Modern Marketing and Why You Need It?
            [ 'are', 'you', 'making', 'these', 'topic', 'mistakes' ],
            // Are You Making These Marketing Mistakes?
            [ 'how', 'to', 'verb', 'topic', 'in', 'x', 'days' ],
            // How to Master Marketing in 30 Days
            // Comparison structures
            [ 'adjective', 'vs', 'adjective', 'which', 'noun', 'is', 'better' ],
            // Modern vs Traditional: Which Strategy is Better?
            [ 'topic', 'vs', 'topic', 'the', 'adjective', 'noun' ],
            // Marketing vs Sales: The Ultimate Guide
            // Listicle variations
            [ 'top', 'x', 'nouns', 'every', 'topic', 'professional', 'needs' ],
            // Top 10 Tools Every Marketing Professional Needs
            [ 'x', 'intensity', 'ways', 'to', 'verb', 'your', 'topic' ],
            // 7 Proven Ways to Boost Your Marketing
            [ 'x', 'adjective', 'topic', 'nouns', 'you', 'should', 'know' ],
            // 5 Essential Marketing Strategies You Should Know
            // Time-based structures
            [ 'the', 'future', 'of', 'topic', 'in', 'timemod' ],
            // The Future of Marketing in 2025
            [ 'topic', 'trends', 'to', 'watch', 'timemod' ],
            // Marketing Trends to Watch in 2025
            [ 'adjective', 'topic', 'nouns', 'for', 'timemod' ],
            // Essential Marketing Strategies for 2025
            // Case study structures
            [ 'how', 'industry', 'verb', 'topic', 'in', 'x', 'days' ],
            // How Healthcare Transformed Marketing in 90 Days
            [ 'how', 'we', 'verb', 'topic', 'by', 'x', 'percent' ],
            // How We Improved Marketing by 300%
            [ 'from', 'zero', 'to', 'adjective', 'a', 'topic', 'success', 'story' ],
            // From Zero to Hero: A Marketing Success Story
            // News-style structures
            [ 'adjective', 'noun', 'transforms', 'industry', 'timemod' ],
            // Revolutionary Strategy Transforms Healthcare in 2025
            [ 'why', 'topic', 'is', 'changing', 'industry', 'timemod' ],
            // Why Marketing is Changing Healthcare This Year
            // Problem-solution structures
            [ 'struggling', 'with', 'topic', 'try', 'these', 'nouns' ],
            // Struggling with Marketing? Try These Strategies
            [ 'the', 'intensity', 'noun', 'to', 'verb', 'your', 'topic' ],
            // The Proven Guide to Master Your Marketing
            [ 'stop', 'wasting', 'time', 'verb', 'topic', 'the', 'adjective', 'way' ],
            // Stop Wasting Time: Master Marketing the Right Way
        ];
        // Randomly pick a structure
        $template = $structures[ array_rand( $structures ) ];
        // Replace keywords with random words
        $title = array_map(
            function ( $word ) use ( $adjectives, $nouns, $verbs, $topics, $industries, $time_modifiers, $intensity_modifiers )
            {
                switch ( $word )
                {
                    case 'adjective':
                        return $adjectives[ array_rand( $adjectives ) ];
                    case 'noun':
                        return $nouns[ array_rand( $nouns ) ];
                    case 'nouns':
                        return $nouns[ array_rand( $nouns ) ];
                    case 'verb':
                        return $verbs[ array_rand( $verbs ) ];
                    case 'topic':
                        return $topics[ array_rand( $topics ) ];
                    case 'industry':
                        return $industries[ array_rand( $industries ) ];
                    case 'timemod':
                        return $time_modifiers[ array_rand( $time_modifiers ) ];
                    case 'intensity':
                        return $intensity_modifiers[ array_rand( $intensity_modifiers ) ];
                    case 'x':
                        return wp_rand( 3, 15 ); // Number
                    case 'ways':
                        return 2 === wp_rand( 2, 4 ) ? 'Ways' : 'Methods';
                    case 'professional':
                        return 2 === wp_rand( 2, 4 ) ? 'Professional' : 'Expert';
                    case 'percent':
                        return wp_rand( 50, 500 ) . '%';
                    default:
                        return $word;
                }
            },
            $template
        );
        // Capitalize the first letter of the sentence and join
        $title_str = ucfirst( implode( ' ', $title ) );

        return $title_str;
    }

    /**
     * Generate random content for posts.
     *
     * @return string Generated content
     */
    private function randomize_content()
    {
        // Randomly select a content type
        $content_types = [ 'listicle', 'howto', 'news', 'opinion', 'casestudy' ];
        $content_type  = $content_types[ array_rand( $content_types ) ];

        // Generate content based on type
        switch ( $content_type )
        {
            case 'listicle':
                $content = $this->generate_listicle_content();
                break;
            case 'howto':
                $content = $this->generate_howto_content();
                break;
            case 'news':
                $content = $this->generate_news_content();
                break;
            case 'opinion':
                $content = $this->generate_opinion_content();
                break;
            case 'casestudy':
                $content = $this->generate_casestudy_content();
                break;
            default:
                $content = $this->generate_standard_content();
        }

        // Calculate metadata
        $word_count   = str_word_count( wp_strip_all_tags( $content ) );
        $reading_time = max( 1, ceil( $word_count / 200 ) ); // Average reading speed: 200 words/min

        // Add metadata as HTML comment
        $metadata = "\n<!-- Content Type: {$content_type} | Word Count: {$word_count} | Reading Time: {$reading_time} min -->\n\n";

        // Append the Content Forge attribution
        $content .= "\n\n<p><em>This is a fake post generated by Content Forge.</em></p>";

        return $metadata . $content;
    }

    /**
     * Get a random sentence from the sentence bank.
     *
     * @param string $category Optional category filter.
     * @return string Random sentence
     */
    private function get_random_sentence( $category = '' )
    {
        $sentences = $this->get_sentence_bank();

        if ( $category && isset( $sentences[ $category ] ) )
        {
            return $sentences[ $category ][ array_rand( $sentences[ $category ] ) ];
        }

        // Get from all categories
        $all_sentences = [];
        foreach ( $sentences as $cat_sentences )
        {
            $all_sentences = array_merge( $all_sentences, $cat_sentences );
        }

        return $all_sentences[ array_rand( $all_sentences ) ];
    }

    /**
     * Get sentence bank organized by category.
     *
     * @return array Categorized sentences
     */
    private function get_sentence_bank()
    {
        return [
            'business'    => [
                'In today\'s fast-paced digital world, businesses need to stay ahead of the competition by implementing innovative strategies.',
                'The key to success lies in understanding your target audience and delivering value that exceeds their expectations.',
                'Data-driven decision making has become essential for companies looking to optimize their operations and maximize ROI.',
                'Customer experience has emerged as a critical differentiator in today\'s competitive marketplace.',
                'Sustainable practices are no longer optional but necessary for long-term business viability.',
                'Digital transformation is not just about technology; it\'s about reimagining business processes.',
                'Leadership in the modern era demands adaptability, empathy, and strategic vision.',
                'Strategic partnerships can accelerate growth and expand market reach.',
                'Change management is critical for successful organizational transformation initiatives.',
                'Risk assessment and mitigation strategies protect businesses from potential threats.',
                'Market research provides the foundation for informed business strategy development.',
                'Competitive analysis helps organizations identify opportunities and threats in their market.',
                'Resource optimization maximizes efficiency while minimizing waste and costs.',
                'Quality assurance processes ensure products meet or exceed customer expectations.',
                'Performance metrics and KPIs provide valuable insights for strategic decision making.',
            ],
            'technology'  => [
                'Technology continues to evolve at an unprecedented rate, creating new opportunities for growth and development.',
                'Automation and artificial intelligence are transforming industries and reshaping the future of work.',
                'Remote work has fundamentally changed how teams collaborate and organizations operate.',
                'Innovation requires a culture that encourages experimentation and embraces calculated risks.',
                'Cybersecurity has become a top priority as organizations face increasingly sophisticated threats.',
                'Cloud computing has democratized access to enterprise-level technology and infrastructure.',
                'Blockchain technology promises to revolutionize various industries beyond cryptocurrency.',
                'Artificial intelligence is augmenting human capabilities rather than replacing them entirely.',
                'Mobile-first design has become essential as smartphone usage continues to dominate.',
                'User experience design plays a crucial role in product adoption and customer satisfaction.',
                'Agile methodologies have transformed project management and software development practices.',
                'The Internet of Things is connecting devices and creating new data streams for analysis.',
                'Machine learning algorithms are enabling predictive analytics and personalized experiences.',
                '5G technology is unlocking new possibilities for connectivity and real-time applications.',
                'Quantum computing represents the next frontier in computational power and problem-solving.',
            ],
            'marketing'   => [
                'Effective communication is the foundation of any successful organization, fostering collaboration and driving results.',
                'Content marketing has proven to be one of the most effective ways to build brand awareness.',
                'Social media platforms continue to evolve, offering new ways to connect with audiences.',
                'E-commerce has revolutionized retail, creating opportunities for businesses of all sizes.',
                'Personalization at scale is now possible thanks to advanced analytics and machine learning.',
                'Brand authenticity resonates more strongly with consumers than traditional advertising.',
                'Omnichannel strategies ensure consistent customer experiences across all touchpoints.',
                'Influencer marketing has become a powerful tool for reaching targeted demographics.',
                'Video content continues to dominate engagement metrics across all platforms.',
                'Email marketing remains one of the highest ROI channels when executed properly.',
                'Search engine optimization is constantly evolving with algorithm updates and user behavior changes.',
                'Conversion rate optimization focuses on maximizing the value of existing traffic.',
                'Marketing automation enables personalized communication at scale.',
                'Customer segmentation allows for more targeted and effective messaging.',
                'A/B testing provides data-driven insights for continuous improvement.',
            ],
            'general'     => [
                'Cross-functional collaboration is essential for delivering complex projects successfully.',
                'Continuous learning has become a necessity in rapidly changing professional landscapes.',
                'Diversity and inclusion initiatives drive innovation and improve organizational performance.',
                'Scalability considerations must be built into systems and processes from the beginning.',
                'Customer feedback loops enable continuous improvement and product refinement.',
                'Environmental consciousness is driving innovation in product design and manufacturing.',
                'The gig economy has created new employment models and changed traditional career paths.',
                'Work-life balance has become a priority for attracting and retaining top talent.',
                'Transparency and accountability build trust with stakeholders and customers.',
                'Ethical considerations are increasingly important in business decision-making.',
                'Global markets present both opportunities and challenges for expanding businesses.',
                'Regulatory compliance requires ongoing attention and adaptation to changing requirements.',
                'Stakeholder engagement is crucial for project success and organizational alignment.',
                'Time management and productivity tools help professionals maximize their effectiveness.',
                'Professional development and upskilling are essential for career advancement.',
            ],
            'transitions' => [
                'Furthermore, this approach has proven effective across multiple industries.',
                'However, it\'s important to consider the potential challenges and limitations.',
                'In addition, organizations must remain flexible and adaptable to changing conditions.',
                'On the other hand, traditional methods still have their place in certain contexts.',
                'Moreover, the benefits extend beyond immediate financial returns.',
                'Nevertheless, careful planning and execution are critical for success.',
                'As a result, companies are seeing significant improvements in key metrics.',
                'Consequently, industry leaders are adopting these practices at an accelerating pace.',
                'Similarly, other sectors are experiencing comparable transformations.',
                'In contrast, outdated approaches are becoming increasingly ineffective.',
            ],
        ];
    }

    /**
     * Generate a paragraph with specified length.
     *
     * @param string $length Paragraph length: 'short', 'medium', or 'long'.
     * @param string $category Optional sentence category.
     * @return string Generated paragraph
     */
    private function generate_paragraph( $length = 'medium', $category = '' )
    {
        $sentence_counts = [
            'short'  => [ 2, 3 ],
            'medium' => [ 4, 5 ],
            'long'   => [ 6, 8 ],
        ];

        $range          = $sentence_counts[ $length ] ?? $sentence_counts[ 'medium' ];
        $sentence_count = wp_rand( $range[ 0 ], $range[ 1 ] );
        $paragraph      = '<p>';

        for ( $i = 0; $i < $sentence_count; $i++ )
        {
            $paragraph .= $this->get_random_sentence( $category ) . ' ';
        }

        $paragraph = trim( $paragraph ) . '</p>';

        return $paragraph;
    }

    /**
     * Generate listicle-style content.
     *
     * @return string Generated content
     */
    private function generate_listicle_content()
    {
        $content    = '';
        $item_count = wp_rand( 5, 10 );

        // Introduction
        $content .= $this->generate_paragraph( 'medium' );
        $content .= "\n\n";

        // List items
        $list_type  = wp_rand( 1, 2 ) === 1 ? 'ol' : 'ul';
        $content   .= "<{$list_type}>\n";

        for ( $i = 0; $i < $item_count; $i++ )
        {
            $content .= '<li><strong>' . $this->get_list_item_title() . '</strong> - ';
            $content .= $this->get_random_sentence() . "</li>\n";
        }

        $content .= "</{$list_type}>\n\n";

        // Conclusion
        $content .= $this->generate_paragraph( 'short' );

        return $content;
    }

    /**
     * Get a random list item title.
     *
     * @return string List item title
     */
    private function get_list_item_title()
    {
        $titles = [
            'Leverage Advanced Analytics',
            'Embrace Digital Transformation',
            'Focus on Customer Experience',
            'Implement Agile Methodologies',
            'Invest in Employee Development',
            'Optimize Your Processes',
            'Build Strategic Partnerships',
            'Prioritize Data Security',
            'Adopt Cloud Solutions',
            'Enhance Communication Channels',
            'Streamline Operations',
            'Develop a Strong Brand',
            'Utilize Automation Tools',
            'Foster Innovation Culture',
            'Measure Key Metrics',
        ];

        return $titles[ array_rand( $titles ) ];
    }

    /**
     * Generate how-to guide content.
     *
     * @return string Generated content
     */
    private function generate_howto_content()
    {
        $content    = '';
        $step_count = wp_rand( 4, 7 );

        // Introduction
        $content .= $this->generate_paragraph( 'medium' );
        $content .= "\n\n";

        // Steps
        for ( $i = 1; $i <= $step_count; $i++ )
        {
            $content .= "<h2>Step {$i}: " . $this->get_step_title() . "</h2>\n\n";
            $content .= $this->generate_paragraph( 'medium' );
            $content .= "\n\n";
        }

        // Conclusion
        $content .= "<h2>Conclusion</h2>\n\n";
        $content .= $this->generate_paragraph( 'short' );

        return $content;
    }

    /**
     * Get a random step title for how-to content.
     *
     * @return string Step title
     */
    private function get_step_title()
    {
        $titles = [
            'Define Your Objectives',
            'Research Your Options',
            'Create a Detailed Plan',
            'Implement Your Strategy',
            'Monitor and Measure Results',
            'Optimize Based on Data',
            'Scale Your Efforts',
            'Gather Stakeholder Feedback',
            'Document Your Process',
            'Train Your Team',
        ];

        return $titles[ array_rand( $titles ) ];
    }

    /**
     * Generate news article content.
     *
     * @return string Generated content
     */
    private function generate_news_content()
    {
        $content = '';

        // Lead paragraph (most important info)
        $content .= $this->generate_paragraph( 'medium' );
        $content .= "\n\n";

        // Supporting paragraphs
        $paragraph_count = wp_rand( 3, 5 );
        for ( $i = 0; $i < $paragraph_count; $i++ )
        {
            $lengths  = [ 'short', 'medium', 'long' ];
            $content .= $this->generate_paragraph( $lengths[ array_rand( $lengths ) ] );
            $content .= "\n\n";
        }

        // Quote
        $content .= '<blockquote><p>"' . $this->get_random_sentence() . '"</p></blockquote>';
        $content .= "\n\n";

        // Final paragraph
        $content .= $this->generate_paragraph( 'short' );

        return $content;
    }

    /**
     * Generate opinion/editorial content.
     *
     * @return string Generated content
     */
    private function generate_opinion_content()
    {
        $content = '';

        // Strong opening thesis
        $content .= $this->generate_paragraph( 'short' );
        $content .= "\n\n";

        // Supporting arguments with subheadings
        $section_count = wp_rand( 3, 4 );
        for ( $i = 0; $i < $section_count; $i++ )
        {
            $content .= '<h2>' . $this->get_opinion_heading() . "</h2>\n\n";
            $content .= $this->generate_paragraph( 'medium' );
            $content .= "\n\n";
            $content .= $this->generate_paragraph( 'short' );
            $content .= "\n\n";
        }

        // Counterargument
        $content .= "<h2>Addressing Concerns</h2>\n\n";
        $content .= $this->generate_paragraph( 'medium' );
        $content .= "\n\n";

        // Strong conclusion
        $content .= $this->generate_paragraph( 'short' );

        return $content;
    }

    /**
     * Get a random opinion heading.
     *
     * @return string Opinion heading
     */
    private function get_opinion_heading()
    {
        $headings = [
            'The Current State of Affairs',
            'Why This Matters Now',
            'The Evidence is Clear',
            'What Experts Are Saying',
            'The Long-Term Implications',
            'A Better Approach',
            'Learning from Success',
            'The Path Forward',
        ];

        return $headings[ array_rand( $headings ) ];
    }

    /**
     * Generate case study content.
     *
     * @return string Generated content
     */
    private function generate_casestudy_content()
    {
        $content = '';

        // Background
        $content .= "<h2>Background</h2>\n\n";
        $content .= $this->generate_paragraph( 'medium' );
        $content .= "\n\n";

        // The Challenge
        $content .= "<h2>The Challenge</h2>\n\n";
        $content .= $this->generate_paragraph( 'medium' );
        $content .= "\n\n";

        // The Solution
        $content .= "<h2>The Solution</h2>\n\n";
        $content .= $this->generate_paragraph( 'long' );
        $content .= "\n\n";

        // Implementation
        $content .= "<h2>Implementation</h2>\n\n";
        $content .= $this->generate_paragraph( 'medium' );
        $content .= "\n\n";

        // Results
        $content .= "<h2>Results</h2>\n\n";
        $content .= '<ul>';
        $content .= '<li>Increased efficiency by ' . wp_rand( 20, 80 ) . '%</li>';
        $content .= '<li>Reduced costs by ' . wp_rand( 15, 50 ) . '%</li>';
        $content .= '<li>Improved customer satisfaction by ' . wp_rand( 25, 70 ) . '%</li>';
        $content .= '<li>Achieved ROI in ' . wp_rand( 3, 12 ) . ' months</li>';
        $content .= "</ul>\n\n";

        // Conclusion
        $content .= "<h2>Key Takeaways</h2>\n\n";
        $content .= $this->generate_paragraph( 'short' );

        return $content;
    }

    /**
     * Generate standard content (fallback).
     *
     * @return string Generated content
     */
    private function generate_standard_content()
    {
        $paragraphs = wp_rand( 3, 6 );
        $content    = '';

        for ( $i = 0; $i < $paragraphs; $i++ )
        {
            $lengths  = [ 'short', 'medium', 'long' ];
            $content .= $this->generate_paragraph( $lengths[ array_rand( $lengths ) ] );

            if ( $i < $paragraphs - 1 )
            {
                $content .= "\n\n";
            }
        }

        return $content;
    }
    /**
     * Delete generated posts by IDs.
     *
     * @param array $object_ids Array of post IDs to delete.
     *
     * @return int Number of items deleted.
     */
    public function delete( array $object_ids )
    {
        $deleted = 0;
        foreach ( $object_ids as $post_id )
        {
            if ( wp_delete_post( $post_id, true ) )
            {
                ++$deleted;
                $this->untrack_generated( $post_id );
            }
        }

        return $deleted;
    }

    /**
     * Track generated post in the custom DB table.
     *
     * @param int $post_id The post ID to track.
     */
    protected function track_generated( $post_id )
    {
        global $wpdb;
        // We use direct DB access here because we are tracking generated posts in a custom table,
        // and there is no WordPress API for this use case. All data is sanitized and prepared.
        Activator::create_tracking_table();
        $post       = get_post( $post_id );
        $data_type  = $post ? $post->post_type : 'post';
        $table_name = $wpdb->prefix . CFORGE_DBNAME;
        $object_id  = intval( $post_id );
        $data_type  = sanitize_key( $data_type );
        $created_at = current_time( 'mysql' );
        $created_by = intval( $this->user_id );
        // Construct SQL without interpolation
        $sql    = "INSERT INTO {$table_name} (object_id, data_type, created_at, created_by) VALUES (%d, %s, %s, %d)";
        $result = $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$table_name} (object_id, data_type, created_at, created_by) VALUES (%d, %s, %s, %d)",
                $object_id,
                $data_type,
                $created_at,
                $created_by
            )
        );

        if ( false === $result )
        {
            // Optionally log or handle the error - removing error_log for production
            // error_log( 'Failed to insert generated post tracking record for post_id: ' . $object_id );
            // For now, we silently handle the error
        }
    }

    /**
     * Remove tracking info for a deleted post.
     *
     * @param int $post_id The post ID to untrack.
     */
    protected function untrack_generated( $post_id )
    {
        global $wpdb;
        $wpdb->delete(
            $wpdb->prefix . CFORGE_DBNAME,
            [
                'object_id' => $post_id,
            ],
            [ '%d' ]
        );
    }
}
