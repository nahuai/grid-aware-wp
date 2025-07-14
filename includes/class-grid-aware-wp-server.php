<?php
/**
 * Server-side functionality for Grid Aware WordPress
 *
 * @package Grid_Aware_WP
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Grid_Aware_WP_Server {
	public function __construct() {
		add_filter( 'render_block', array( $this, 'enqueue_lite_youtube_assets' ), 10, 2 );
		add_filter( 'render_block_core/image', array( $this, 'filter_image_block' ), 999, 2 );
		add_filter( 'render_block_core/embed', array( $this, 'filter_youtube_embed_block' ), 999, 2 );
	}

	public function enqueue_lite_youtube_assets( $block_content, $block ) {
		if ( 'core/embed' === $block['blockName'] ) {
			$is_youtube = false;
			if ( preg_match( '/youtube\.com|youtu\.be/', $block_content ) ) {
				$is_youtube = true;
			}
			if ( $is_youtube ) {
				wp_enqueue_script( 'lite-youtube' );
				wp_enqueue_style( 'lite-youtube' );
			}
		}
		return $block_content;
	}

	public function filter_image_block( $block_content, $block ) {
		$post_id = get_the_ID();
		$page_options = get_post_meta( $post_id, 'grid_aware_wp_page_options', true );
		$global_options = get_option(
			'grid_aware_wp_options',
			array(
				'images'     => '1',
				'videos'     => '1',
				'typography' => '1',
			)
		);
		$settings = ! empty( $page_options ) ? $page_options : $global_options;
		if ( ! isset( $settings['images'] ) || '0' === $settings['images'] ) {
			return $block_content;
		}
		$effective_intensity = isset( $GLOBALS['grid_aware_wp_effective_intensity'] ) ? $GLOBALS['grid_aware_wp_effective_intensity'] : 'live';
		$image_id = null;
		if ( preg_match( '/wp-image-(\d+)/', $block_content, $image_id_matches ) ) {
			$image_id = $image_id_matches[1];
		}
		$original_width = '';
		$original_height = '';
		$original_style = '';
		$aspect_ratio = '';
		if ( $image_id ) {
			$image_path = get_attached_file( $image_id );
			$size = @getimagesize( $image_path );
			if ( $size ) {
				$original_width = $size[0];
				$original_height = $size[1];
				$aspect_ratio = $original_width . ' / ' . $original_height;
			}
		}
		$displayed_width = '';
		if ( preg_match( '/width="(\d+)"/', $block_content, $width_matches ) ) {
			$displayed_width = $width_matches[1] . 'px';
		} else {
			$displayed_width = '100%';
		}
		if ( ! $original_height && preg_match( '/height="(\d+)"/', $block_content, $height_matches ) ) {
			$original_height = $height_matches[1];
		}
		if ( ! $aspect_ratio && $original_width && $original_height ) {
			$aspect_ratio = $original_width . ' / ' . $original_height;
		}
		$placeholder_style = '';
		if ( $displayed_width ) {
			$placeholder_style .= '--image-width: ' . $displayed_width . '; ';
		}
		if ( $aspect_ratio ) {
			$placeholder_style .= '--aspect-ratio: ' . $aspect_ratio . '; ';
		}
		if ( $original_style ) {
			$placeholder_style .= $original_style . '; ';
		}
		$alt_text = '';
		$caption = '';
		if ( preg_match( '/<img[^>]+alt="([^"]*)"[^>]*>/i', $block_content, $alt_matches ) ) {
			$alt_text = $alt_matches[1];
		}
		if ( preg_match( '/<figcaption[^>]*>(.*?)<\/figcaption>/i', $block_content, $caption_matches ) ) {
			$caption = $caption_matches[1];
		}
		// Remove figcaption from block_content for placeholder to avoid duplicate captions
		$block_content_no_caption = preg_replace( '/<figcaption[^>]*>.*?<\/figcaption>/is', '', $block_content );
		if ( 'high' === $effective_intensity ) {
			$placeholder_html = sprintf(
				'<div class="grid-aware-image-placeholder" data-original-image="%s" onclick="gridAwareWPLoadImage(this)"%s>
					<div class="placeholder-content">
						<div class="placeholder-icon">
							<svg width="78" height="67" viewBox="0 0 78 67" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M72 0.5H6C4.4087 0.5 2.88258 1.13214 1.75736 2.25736C0.632141 3.38258 0 4.9087 0 6.5V60.5C0 62.0913 0.632141 63.6174 1.75736 64.7426C2.88258 65.8679 4.4087 66.5 6 66.5H72C73.5913 66.5 75.1174 65.8679 76.2426 64.7426C77.3679 63.6174 78 62.0913 78 60.5V6.5C78 4.9087 77.3679 3.38258 76.2426 2.25736C75.1174 1.13214 73.5913 0.5 72 0.5ZM49.5 18.5C50.39 18.5 51.26 18.7639 52.0001 19.2584C52.7401 19.7529 53.3169 20.4557 53.6575 21.2779C53.9981 22.1002 54.0872 23.005 53.9135 23.8779C53.7399 24.7508 53.3113 25.5526 52.682 26.182C52.0526 26.8113 51.2508 27.2399 50.3779 27.4135C49.505 27.5872 48.6002 27.4981 47.7779 27.1575C46.9557 26.8169 46.2529 26.2401 45.7584 25.5001C45.2639 24.76 45 23.89 45 23C45 21.8065 45.4741 20.6619 46.318 19.818C47.1619 18.9741 48.3065 18.5 49.5 18.5ZM72 60.5H6V45.7588L23.3775 28.3775C23.6561 28.0986 23.987 27.8773 24.3512 27.7263C24.7154 27.5753 25.1058 27.4976 25.5 27.4976C25.8942 27.4976 26.2846 27.5753 26.6488 27.7263C27.013 27.8773 27.3439 28.0986 27.6225 28.3775L52.875 53.6225C53.4379 54.1854 54.2014 54.5017 54.9975 54.5017C55.7936 54.5017 56.5571 54.1854 57.12 53.6225C57.6829 53.0596 57.9992 52.2961 57.9992 51.5C57.9992 50.7039 57.6829 49.9404 57.12 49.3775L50.4975 42.7588L55.875 37.3775C56.4376 36.8153 57.2003 36.4995 57.9956 36.4995C58.7909 36.4995 59.5537 36.8153 60.1162 37.3775L72 49.2763V60.5Z" fill="#E3E3E3"/></svg>
						</div>
						' . ( strlen( trim( $alt_text ) ) > 0 ? '<div class="placeholder-alt">' . esc_html( $alt_text ) . '</div>' : '<div class="placeholder-alt">' . esc_html__( 'No ALT text was provided', 'grid-aware-wp' ) . '</div>' ) . '
						' . ( ! empty( $caption ) ? '<div class="placeholder-caption">' . wp_kses_post( $caption ) . '</div>' : '' ) . '
						<div class="placeholder-description">
							' . esc_html__( "This image hasn't been loaded due to the", 'grid-aware-wp' ) . ' <strong>' . esc_html__( 'high grid intensity.', 'grid-aware-wp' ) . '</strong>
						</div>
						<button class="placeholder-load-btn" type="button">' . esc_html__( 'LOAD IMAGE', 'grid-aware-wp' ) . '</button>
					</div>
				</div>',
				esc_attr( $block_content ), // Use the original content with caption for restoration
				! empty( $placeholder_style ) ? ' style="' . esc_attr( $placeholder_style ) . '"' : ''
			);
			$block_content = preg_replace(
				'/<img[^>]+>/i',
				$placeholder_html,
				$block_content_no_caption // Only the placeholder rendering should not have the caption
			);
			return $block_content;
		}
		if ( 'medium' === $effective_intensity ) {
			$overlay_html = sprintf(
				'<div class="medium-overlay">
					%s
					<div class="placeholder-description">%s <strong>%s</strong></div>
					<button class="placeholder-load-btn" type="button">%s</button>
				</div>',
				! empty( $alt_text ) ? '<div class="placeholder-alt">' . esc_html( $alt_text ) . '</div>' : '',
				esc_html__( 'This image has been loaded in low quality due to the', 'grid-aware-wp' ),
				esc_html__( 'medium grid intensity.', 'grid-aware-wp' ),
				esc_html__( 'Load full quality image', 'grid-aware-wp' )
			);
			$block_content = sprintf(
				'<div class="grid-aware-image-blurred" data-original-image="%s" onclick="gridAwareWPLoadImage(this)">%s%s</div>',
				esc_attr( $block_content ),
				$block_content,
				$overlay_html
			);
			return $block_content;
		}
		if ( 'low' === $effective_intensity ) {
			return $block_content;
		}
		if ( 'live' === $effective_intensity ) {
			$block_content = $this->convert_youtube_to_nocookie( $block_content );
			if ( ! preg_match( '/loading="lazy"/i', $block_content ) ) {
				$block_content = preg_replace(
					'/<iframe([^>]+)>/i',
					'<iframe$1 loading="lazy">',
					$block_content
				);
			}
		}
		return $block_content;
	}

	public function filter_youtube_embed_block( $block_content, $block ) {
		$post_id = get_the_ID();
		$page_options = get_post_meta( $post_id, 'grid_aware_wp_page_options', true );
		$global_options = get_option(
			'grid_aware_wp_options',
			array(
				'images'     => '1',
				'videos'     => '1',
				'typography' => '1',
			)
		);
		if ( ! empty( $page_options ) && isset( $page_options['videos'] ) ) {
			$settings = $page_options;
		} else {
			$settings = $global_options;
		}
		if ( ! isset( $settings['videos'] ) || '0' === $settings['videos'] ) {
			return $block_content;
		}
		$effective_intensity = isset( $GLOBALS['grid_aware_wp_effective_intensity'] ) ? $GLOBALS['grid_aware_wp_effective_intensity'] : 'live';
		$video_id = '';
		$video_title = '';
		if ( isset( $block['attrs']['url'] ) && preg_match( '/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?|shorts)\/|.*[?&]v=)|youtu\.be\/)([A-Za-z0-9_-]{11})/', $block['attrs']['url'], $matches ) ) {
			$video_id = $matches[1];
		}
		if ( isset( $block['attrs']['title'] ) ) {
			$video_title = $block['attrs']['title'];
		}
		// Fallback: extract title from block content if not found in attributes
		if ( empty( $video_title ) && preg_match( '/title="([^"]+)"/', $block_content, $title_matches ) ) {
			$video_title = $title_matches[1];
		}
		if ( 'high' === $effective_intensity ) {
			$video_width = '';
			$video_height = '';
			$video_style = '';
			if ( preg_match( '/width="(\d+)"/', $block_content, $width_matches ) ) {
				$video_width = $width_matches[1];
			}
			if ( preg_match( '/height="(\d+)"/', $block_content, $height_matches ) ) {
				$video_height = $height_matches[1];
			}
			if ( preg_match( '/style="([^"]*)"/', $block_content, $style_matches ) ) {
				$video_style = $style_matches[1];
			}
			$placeholder_style = '';
			if ( $video_width ) {
				$placeholder_style .= '--video-width: ' . $video_width . 'px; ';
			}
			if ( $video_style ) {
				$placeholder_style .= $video_style . '; ';
			}
			$original_content_with_nocookie = $this->convert_youtube_to_nocookie( $block_content );
			$placeholder_html = sprintf(
				'<div class="grid-aware-video-placeholder" data-original-video="%s" onclick="gridAwareWPLoadVideo(this)"%s>
					<div class="placeholder-content">
						<div class="placeholder-icon">
							<svg width="95" height="81" viewBox="0 0 95 81" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M94.625 76.75C94.625 77.7114 94.2431 78.6334 93.5633 79.3133C92.8834 79.9931 91.9614 80.375 91 80.375H4C3.03859 80.375 2.11656 79.9931 1.43674 79.3133C0.756918 78.6334 0.375 77.7114 0.375 76.75C0.375 75.7886 0.756918 74.8666 1.43674 74.1867C2.11656 73.5069 3.03859 73.125 4 73.125H91C91.9614 73.125 92.8834 73.5069 93.5633 74.1867C94.2431 74.8666 94.625 75.7886 94.625 76.75ZM94.625 7.875V58.625C94.625 60.5478 93.8612 62.3919 92.5015 63.7515C91.1419 65.1112 89.2978 65.875 87.375 65.875H7.625C5.70218 65.875 3.85811 65.1112 2.49848 63.7515C1.13884 62.3919 0.375 60.5478 0.375 58.625V7.875C0.375 5.95218 1.13884 4.10811 2.49848 2.74848C3.85811 1.38884 5.70218 0.625 7.625 0.625H87.375C89.2978 0.625 91.1419 1.38884 92.5015 2.74848C93.8612 4.10811 94.625 5.95218 94.625 7.875ZM63.8125 33.25C63.8123 32.6675 63.6718 32.0937 63.4029 31.5771C63.1339 31.0604 62.7444 30.6162 62.2673 30.282L44.1423 17.5945C43.5992 17.2141 42.9621 16.9899 42.3004 16.9463C41.6387 16.9028 40.9777 17.0416 40.3895 17.3477C39.8012 17.6537 39.3081 18.1153 38.9639 18.6821C38.6198 19.249 38.4377 19.8994 38.4375 20.5625V45.9375C38.4377 46.6006 38.6198 47.251 38.9639 47.8179C39.3081 48.3847 39.8012 48.8463 40.3895 49.1523C40.9777 49.4584 41.6387 49.5972 42.3004 49.5537C42.9621 49.5101 43.5992 49.2859 44.1423 48.9055L62.2673 36.218C62.7444 35.8838 63.1339 35.4396 63.4029 34.9229C63.6718 34.4063 63.8123 33.8325 63.8125 33.25Z" fill="#E3E3E3"/></svg>
						</div>
						' . ( ! empty( $video_title ) ? '<div class="placeholder-alt">' . esc_html( $video_title ) . '</div>' : '' ) . '
						<div class="placeholder-description">
							' . esc_html__( "This video hasn't been loaded due to the", 'grid-aware-wp' ) . ' <strong>' . esc_html__( 'high grid intensity.', 'grid-aware-wp' ) . '</strong>
						</div>
						<button class="placeholder-load-btn" type="button">' . esc_html__( 'LOAD VIDEO', 'grid-aware-wp' ) . '</button>
					</div>
				</div>',
				esc_attr( $original_content_with_nocookie ),
				! empty( $placeholder_style ) ? ' style="' . esc_attr( $placeholder_style ) . '"' : ''
			);
			return $placeholder_html;
		}
		if ( 'medium' === $effective_intensity ) {
			if ( ! empty( $video_id ) ) {
				$video_width = '';
				$video_height = '';
				$video_style = '';
				if ( preg_match( '/width="(\d+)"/', $block_content, $width_matches ) ) {
					$video_width = $width_matches[1];
				}
				if ( preg_match( '/height="(\d+)"/', $block_content, $height_matches ) ) {
					$video_height = $height_matches[1];
				}
				if ( preg_match( '/style="([^"]*)"/', $block_content, $style_matches ) ) {
					$video_style = $style_matches[1];
				}
				$thumbnail_style = '';
				$thumbnail_style .= '--video-width: 100%; ';
				if ( $video_style ) {
					$thumbnail_style .= $video_style . '; ';
				}
				$thumbnail_url = 'https://img.youtube.com/vi/' . $video_id . '/maxresdefault.jpg';
				$original_content_with_nocookie = $this->convert_youtube_to_nocookie( $block_content );
				if ( empty( $video_title ) ) {
					return sprintf(
						'<div class="grid-aware-video-thumbnail" data-original-video="%s" onclick="gridAwareWPLoadVideo(this)"%s>
							<img src="%s" alt="%s" loading="lazy" />
							<div class="medium-overlay">
								%s
								<div class="placeholder-description">%s <strong>%s</strong></div>
								<button class="placeholder-load-btn" type="button">%s</button>
							</div>
						</div>',
						esc_attr( $original_content_with_nocookie ),
						! empty( $thumbnail_style ) ? ' style="' . esc_attr( $thumbnail_style ) . '"' : '',
						esc_url( $thumbnail_url ),
						esc_attr__( 'YouTube video thumbnail', 'grid-aware-wp' ),
						'',
						esc_html__( 'This video has been loaded in low quality due to the', 'grid-aware-wp' ),
						esc_html__( 'medium grid intensity.', 'grid-aware-wp' ),
						esc_html__( 'Load video', 'grid-aware-wp' )
					);
				}
				return sprintf(
					'<div class="grid-aware-video-thumbnail" data-original-video="%s" onclick="gridAwareWPLoadVideo(this)"%s>
						<img src="%s" alt="%s" loading="lazy" />
						<div class="medium-overlay">
							%s
							<div class="placeholder-description">%s <strong>%s</strong></div>
							<button class="placeholder-load-btn" type="button">%s</button>
						</div>
					</div>',
					esc_attr( $original_content_with_nocookie ),
					! empty( $thumbnail_style ) ? ' style="' . esc_attr( $thumbnail_style ) . '"' : '',
					esc_url( $thumbnail_url ),
					esc_attr( $video_title ),
					! empty( $video_title ) ? '<div class="placeholder-alt">' . esc_html( $video_title ) . '</div>' : '',
					esc_html__( 'This video has been loaded in low quality due to the', 'grid-aware-wp' ),
					esc_html__( 'medium grid intensity.', 'grid-aware-wp' ),
					esc_html__( 'Load video', 'grid-aware-wp' )
				);
			}
			$block_content = $this->convert_youtube_to_nocookie( $block_content );
			if ( ! preg_match( '/loading="lazy"/i', $block_content ) ) {
				$block_content = preg_replace(
					'/<iframe([^>]+)>/i',
					'<iframe$1 loading="lazy">',
					$block_content
				);
			}
		}
		if ( 'low' === $effective_intensity ) {
			return $block_content;
		}
		if ( 'live' === $effective_intensity ) {
			$block_content = $this->convert_youtube_to_nocookie( $block_content );
			if ( ! preg_match( '/loading="lazy"/i', $block_content ) ) {
				$block_content = preg_replace(
					'/<iframe([^>]+)>/i',
					'<iframe$1 loading="lazy">',
					$block_content
				);
			}
		}
		if ( $video_id ) {
			return $this->lite_youtube_html( $video_id, $video_title );
		}
		return $block_content;
	}

	private function convert_youtube_to_nocookie( $content ) {
		$processor = new \WP_HTML_Tag_Processor( $content );
		while ( $processor->next_tag( 'iframe' ) ) {
			$src = $processor->get_attribute( 'src' );
			if ( $src && ( strpos( $src, 'youtube.com' ) !== false || strpos( $src, 'youtu.be' ) !== false ) ) {
				$src = str_replace( 'youtube.com', 'youtube-nocookie.com', $src );
				$src = str_replace( 'youtu.be', 'youtube-nocookie.com', $src );
				if ( strpos( $src, '?feature=oembed' ) !== false ) {
					$src = str_replace( '?feature=oembed', '?feature=oembed&rel=0', $src );
				} elseif ( strpos( $src, '?' ) !== false ) {
					$src .= '&rel=0';
				} else {
					$src .= '?rel=0';
				}
				$processor->set_attribute( 'src', $src );
			}
		}
		return $processor->get_updated_html();
	}

	private function lite_youtube_html( $video_id, $video_title = '' ) {
		if ( empty( $video_title ) ) {
			$video_title = __( 'YouTube video', 'grid-aware-wp' );
		}
		return sprintf(
			'<lite-youtube videoid="%s" style="width:100%%;aspect-ratio:16/9;" title="%s"></lite-youtube>',
			esc_attr( $video_id ),
			esc_attr( $video_title )
		);
	}
}
/**
 * Filter theme.json data to use system fonts when grid intensity is high
 */
function grid_aware_wp_filter_theme_json_fonts( $theme_json ) {
	// Determine the effective grid intensity for this request
	if ( isset( $GLOBALS['grid_aware_wp_effective_intensity'] ) ) {
		$effective_intensity = $GLOBALS['grid_aware_wp_effective_intensity'];
	} else {
		$effective_intensity = 'live';
	}

	// Get current page/post ID
	$post_id = get_the_ID();

	// Get settings - first check page-specific settings, then fallback to global settings
	$page_options = get_post_meta( $post_id, 'grid_aware_wp_page_options', true );
	$global_options = get_option(
		'grid_aware_wp_options',
		array(
			'images'     => '1',
			'videos'     => '1',
			'typography' => '1',
		)
	);

	// Use page-specific settings if available, otherwise use global settings
	$settings = ! empty( $page_options ) ? $page_options : $global_options;

	// If typography is disabled or not set, return original theme.json
	if ( ! isset( $settings['typography'] ) || '0' === $settings['typography'] ) {
		return $theme_json;
	}

	// If grid intensity is high, replace all font families with system fonts
	if ( 'high' === $effective_intensity ) {
		$new_data = array(
			'version'  => 2,
			'settings' => array(
				'typography' => array(
					'fontFamilies' => array(
						array(
							'fontFamily' => 'Helvetica, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif',
							'name'       => 'System Font',
							'slug'       => 'system',
						),
					),
				),
			),
			'styles' => array(
				'typography' => array(
					'fontFamily' => 'var:preset|font-family|system',
				),
			),
		);

		// Update the theme.json data with our system font configuration
		return $theme_json->update_with( $new_data );
	}

	return $theme_json;
}

/**
 * Apply the theme.json filter after theme setup
 */
function grid_aware_wp_apply_theme_json_filters() {
	// Apply filter to all theme.json data sources
	add_filter( 'wp_theme_json_data_theme', 'grid_aware_wp_filter_theme_json_fonts' );
	add_filter( 'wp_theme_json_data_user', 'grid_aware_wp_filter_theme_json_fonts' );
	add_filter( 'wp_theme_json_data_global', 'grid_aware_wp_filter_theme_json_fonts' );
}
add_action( 'after_setup_theme', 'grid_aware_wp_apply_theme_json_filters' );
