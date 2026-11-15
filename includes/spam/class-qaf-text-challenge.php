<?php
/**
 * Text questions (QUILL's wordpress.md 13a.6, WP-R-039, decision 14): a
 * question in words, answered in words, which works the same by ear, by
 * braille, by switch and by voice.
 *
 * The honest limit: any text a screen reader can read, an AI model can
 * read and answer, so no question stops a determined bot. What it does well
 * is stop cheap scripts and cost the rest time. So it is one layer that is
 * asked only when the quiet checks are unsure (or always, or never, as a
 * site chooses for a form), never the gate.
 *
 * - Made fresh each time from templates and word lists, all translatable,
 *   in four kinds: everyday sense, an instruction about the question
 *   itself, choosing from a short list (also offered as radio buttons), and
 *   putting things in order. Maths only when a site turns it on, because
 *   numbers are a barrier for people with dyscalculia. A site can add its
 *   own questions in Settings > Spam.
 * - The answer never reaches the page. The site keeps it with a one-time
 *   id that lasts 15 minutes, tied to the form, the page's visit id and the
 *   browser. An expired question is simply replaced, never counted against
 *   the person, so there is no time limit on them.
 * - Answers are matched loosely: capitals, spaces, punctuation, numbers as
 *   digits or words, "a", "an" and "the", a slip of a letter or two, and
 *   any of several right answers.
 * - Each wrong answer makes the next proof of work longer and the rate
 *   limit tighter, while a person sees the same gentle question. After
 *   three tries the form offers Send Anyway, and the entry is held for a
 *   person to read: nobody is locked out.
 * - No hidden "if you are a bot" text: screen readers read hidden text too.
 *
 * @package QAF
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * QAF_Text_Challenge class.
 */
class QAF_Text_Challenge {

	/** A question lasts this long. */
	const TTL = 900;

	/** After this many wrong answers, Send Anyway is offered. */
	const SEND_ANYWAY_AFTER = 3; // The default; a site may choose 1 to 10 (QAF_Spam_Settings, send_anyway_after).

	/** How long a week's numbers are kept: a quarter and a little more, so twelve past weeks can be shown. */
	const STATS_TTL_DAYS = 100;

	/** The most weeks the weeks route gives, this one included. */
	const HISTORY_MAX = 13;

	/** @var QAF_Spam_Settings */
	private $settings;

	/** @var QAF_Spam_Store */
	private $store;

	/** @var QAF_Spam_Failures */
	private $failures;

	/** @var callable fn( $min, $max ) => int */
	private $random;

	/** @var callable fn() => int */
	private $clock;

	/**
	 * @param QAF_Spam_Settings $settings Settings.
	 * @param QAF_Spam_Store    $store    Store.
	 * @param QAF_Spam_Failures $failures Wrong answers per address.
	 * @param callable|null     $random   fn( $min, $max ) (tests).
	 * @param callable|null     $clock    fn() => time (tests).
	 */
	public function __construct( QAF_Spam_Settings $settings, QAF_Spam_Store $store, QAF_Spam_Failures $failures, $random = null, $clock = null ) {
		$this->settings = $settings;
		$this->store    = $store;
		$this->failures = $failures;
		$this->random   = $random ?: 'random_int';
		$this->clock    = $clock ?: 'time';
	}

	private function rand( $min, $max ) {
		return (int) call_user_func( $this->random, (int) $min, (int) $max );
	}

	private function pick( $list ) {
		$list = array_values( $list );
		return $list[ $this->rand( 0, count( $list ) - 1 ) ];
	}

	private function shuffled( $list ) {
		$list = array_values( $list );
		for ( $i = count( $list ) - 1; $i > 0; $i-- ) {
			$j          = $this->rand( 0, $i );
			$t          = $list[ $i ];
			$list[ $i ] = $list[ $j ];
			$list[ $j ] = $t;
		}
		return $list;
	}

	private function now() {
		return (int) call_user_func( $this->clock );
	}

	// The question bank.

	/**
	 * The site's own questions: one per line, "Question | answer | another answer".
	 *
	 * @return array[] question, answers.
	 */
	public function site_questions() {
		$out = array();
		foreach ( self::bank_items( (string) $this->settings->get( 'challenge_bank' ) ) as $item ) {
			$out[] = array( 'question' => $item['question'], 'answers' => $item['answers'] );
		}
		return $out;
	}

	/**
	 * The bank as items the REST routes can name: each question with its
	 * answers and an id made from its words, so an edit or a delete names
	 * the version of the question the writer read (a question changed since
	 * has another id, and the route says it is not there). A line with no
	 * answer is not a question and is left out, as site_questions() always
	 * did; the same question twice is one question.
	 *
	 * @param string $bank The saved bank, one "Question | answer | another" per line.
	 * @return array[] id, question, answers.
	 */
	public static function bank_items( $bank ) {
		$out  = array();
		$seen = array();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) $bank ) as $line ) {
			$parts = array_map( 'trim', explode( '|', $line ) );
			$q     = array_shift( $parts );
			$parts = array_values( array_filter( $parts, 'strlen' ) );
			if ( '' === $q || ! $parts ) {
				continue;
			}
			$id = self::bank_id( $q, $parts );
			if ( isset( $seen[ $id ] ) ) {
				continue;
			}
			$seen[ $id ] = true;
			$out[]       = array( 'id' => $id, 'question' => $q, 'answers' => $parts );
		}
		return $out;
	}

	/**
	 * A question's id: 12 hex characters from its words and answers.
	 *
	 * @param string   $question Question.
	 * @param string[] $answers  Answers.
	 * @return string
	 */
	public static function bank_id( $question, $answers ) {
		return substr( sha1( $question . '|' . implode( '|', $answers ) ), 0, 12 );
	}

	/**
	 * Items back to the saved form, one line each.
	 *
	 * @param array[] $items question, answers.
	 * @return string
	 */
	public static function bank_text( $items ) {
		$lines = array();
		foreach ( $items as $item ) {
			$lines[] = $item['question'] . ' | ' . implode( ' | ', $item['answers'] );
		}
		return implode( "\n", $lines );
	}

	/**
	 * The kinds that can be asked.
	 *
	 * @return string[]
	 */
	public function kinds() {
		$kinds = array();
		foreach ( (array) $this->settings->get( 'challenge_kinds' ) as $kind => $on ) {
			if ( $on ) {
				$kinds[] = $kind;
			}
		}
		if ( $this->site_questions() ) {
			$kinds[] = 'site';
		}
		return $kinds ?: array( 'sense' );
	}

	/** Everyday sense: two things, one plainly more so. */
	private static function sense_bank() {
		return array(
			array(
				/* translators: 1 and 2: two things, such as "snow" and "soup". */
				'template' => __( 'Which is colder, %1$s or %2$s?', 'quill-accessible-forms' ),
				'more'     => array( __( 'snow', 'quill-accessible-forms' ), __( 'ice', 'quill-accessible-forms' ), __( 'frost', 'quill-accessible-forms' ), __( 'an icicle', 'quill-accessible-forms' ) ),
				'less'     => array( __( 'soup', 'quill-accessible-forms' ), __( 'toast', 'quill-accessible-forms' ), __( 'hot tea', 'quill-accessible-forms' ), __( 'a campfire', 'quill-accessible-forms' ) ),
			),
			array(
				/* translators: 1 and 2: two things. */
				'template' => __( 'Which is heavier, %1$s or %2$s?', 'quill-accessible-forms' ),
				'more'     => array( __( 'an elephant', 'quill-accessible-forms' ), __( 'a car', 'quill-accessible-forms' ), __( 'a piano', 'quill-accessible-forms' ), __( 'a horse', 'quill-accessible-forms' ) ),
				'less'     => array( __( 'a feather', 'quill-accessible-forms' ), __( 'a leaf', 'quill-accessible-forms' ), __( 'a coin', 'quill-accessible-forms' ), __( 'a paper clip', 'quill-accessible-forms' ) ),
			),
			array(
				/* translators: 1 and 2: two things. */
				'template' => __( 'Which is bigger, %1$s or %2$s?', 'quill-accessible-forms' ),
				'more'     => array( __( 'a mountain', 'quill-accessible-forms' ), __( 'a house', 'quill-accessible-forms' ), __( 'a bus', 'quill-accessible-forms' ), __( 'a whale', 'quill-accessible-forms' ) ),
				'less'     => array( __( 'a pebble', 'quill-accessible-forms' ), __( 'a mouse', 'quill-accessible-forms' ), __( 'an ant', 'quill-accessible-forms' ), __( 'a button', 'quill-accessible-forms' ) ),
			),
			array(
				/* translators: 1 and 2: two things. */
				'template' => __( 'Which is louder, %1$s or %2$s?', 'quill-accessible-forms' ),
				'more'     => array( __( 'thunder', 'quill-accessible-forms' ), __( 'a drum', 'quill-accessible-forms' ), __( 'a siren', 'quill-accessible-forms' ), __( 'a trumpet', 'quill-accessible-forms' ) ),
				'less'     => array( __( 'a whisper', 'quill-accessible-forms' ), __( 'a sigh', 'quill-accessible-forms' ), __( 'a ticking watch', 'quill-accessible-forms' ), __( 'a falling leaf', 'quill-accessible-forms' ) ),
			),
			array(
				/* translators: 1 and 2: two things. */
				'template' => __( 'Which is faster, %1$s or %2$s?', 'quill-accessible-forms' ),
				'more'     => array( __( 'a car', 'quill-accessible-forms' ), __( 'a train', 'quill-accessible-forms' ), __( 'an airplane', 'quill-accessible-forms' ), __( 'a cheetah', 'quill-accessible-forms' ) ),
				'less'     => array( __( 'a snail', 'quill-accessible-forms' ), __( 'a tortoise', 'quill-accessible-forms' ), __( 'a slug', 'quill-accessible-forms' ), __( 'a sloth', 'quill-accessible-forms' ) ),
			),
		);
	}

	/** Things to choose among: one belongs to the group, the others are objects. */
	private static function choice_bank() {
		return array(
			'groups'  => array(
				array( 'name' => __( 'an animal', 'quill-accessible-forms' ), 'words' => array( __( 'horse', 'quill-accessible-forms' ), __( 'dog', 'quill-accessible-forms' ), __( 'cow', 'quill-accessible-forms' ), __( 'sheep', 'quill-accessible-forms' ), __( 'rabbit', 'quill-accessible-forms' ), __( 'duck', 'quill-accessible-forms' ), __( 'goat', 'quill-accessible-forms' ) ) ),
				array( 'name' => __( 'a color', 'quill-accessible-forms' ), 'words' => array( __( 'red', 'quill-accessible-forms' ), __( 'blue', 'quill-accessible-forms' ), __( 'green', 'quill-accessible-forms' ), __( 'yellow', 'quill-accessible-forms' ), __( 'purple', 'quill-accessible-forms' ) ) ),
				array( 'name' => __( 'a fruit', 'quill-accessible-forms' ), 'words' => array( __( 'apple', 'quill-accessible-forms' ), __( 'banana', 'quill-accessible-forms' ), __( 'pear', 'quill-accessible-forms' ), __( 'grape', 'quill-accessible-forms' ), __( 'cherry', 'quill-accessible-forms' ), __( 'lemon', 'quill-accessible-forms' ) ) ),
			),
			'objects' => array( __( 'chair', 'quill-accessible-forms' ), __( 'window', 'quill-accessible-forms' ), __( 'spoon', 'quill-accessible-forms' ), __( 'table', 'quill-accessible-forms' ), __( 'door', 'quill-accessible-forms' ), __( 'cup', 'quill-accessible-forms' ), __( 'book', 'quill-accessible-forms' ), __( 'lamp', 'quill-accessible-forms' ), __( 'pencil', 'quill-accessible-forms' ), __( 'bucket', 'quill-accessible-forms' ), __( 'brick', 'quill-accessible-forms' ), __( 'kettle', 'quill-accessible-forms' ) ),
		);
	}

	/** Things with an order everyone knows. */
	private static function order_bank() {
		return array(
			array( 'way' => __( 'smallest first', 'quill-accessible-forms' ), 'items' => array( __( 'house', 'quill-accessible-forms' ), __( 'town', 'quill-accessible-forms' ), __( 'city', 'quill-accessible-forms' ) ) ),
			array( 'way' => __( 'smallest first', 'quill-accessible-forms' ), 'items' => array( __( 'mouse', 'quill-accessible-forms' ), __( 'dog', 'quill-accessible-forms' ), __( 'horse', 'quill-accessible-forms' ) ) ),
			array( 'way' => __( 'smallest first', 'quill-accessible-forms' ), 'items' => array( __( 'seed', 'quill-accessible-forms' ), __( 'bush', 'quill-accessible-forms' ), __( 'tree', 'quill-accessible-forms' ) ) ),
			array( 'way' => __( 'earliest in the day first', 'quill-accessible-forms' ), 'items' => array( __( 'breakfast', 'quill-accessible-forms' ), __( 'lunch', 'quill-accessible-forms' ), __( 'dinner', 'quill-accessible-forms' ) ) ),
			array( 'way' => __( 'earliest in the day first', 'quill-accessible-forms' ), 'items' => array( __( 'morning', 'quill-accessible-forms' ), __( 'afternoon', 'quill-accessible-forms' ), __( 'evening', 'quill-accessible-forms' ) ) ),
			array( 'way' => __( 'youngest first', 'quill-accessible-forms' ), 'items' => array( __( 'baby', 'quill-accessible-forms' ), __( 'child', 'quill-accessible-forms' ), __( 'adult', 'quill-accessible-forms' ) ) ),
			array( 'way' => __( 'shortest first', 'quill-accessible-forms' ), 'items' => array( __( 'minute', 'quill-accessible-forms' ), __( 'hour', 'quill-accessible-forms' ), __( 'day', 'quill-accessible-forms' ) ) ),
			array( 'way' => __( 'shortest first', 'quill-accessible-forms' ), 'items' => array( __( 'day', 'quill-accessible-forms' ), __( 'week', 'quill-accessible-forms' ), __( 'month', 'quill-accessible-forms' ) ) ),
		);
	}

	/** Number words from zero to twenty, in the site's language. */
	public static function number_words() {
		$words = preg_split( '/\s+/', trim( _x( 'zero one two three four five six seven eight nine ten eleven twelve thirteen fourteen fifteen sixteen seventeen eighteen nineteen twenty', 'number words from 0 to 20, separated by spaces, used to read answers', 'quill-accessible-forms' ) ) );
		return count( $words ) === 21 ? $words : array( 'zero', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten', 'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen', 'twenty' );
	}

	private static function list_text( $items ) {
		return implode( _x( ', ', 'separator between items in a question', 'quill-accessible-forms' ), $items );
	}

	/**
	 * A new question (not stored).
	 *
	 * @param string|null $kind A kind, or null for any enabled one.
	 * @return array kind, question, answers, options (for loose matching), choices (radio buttons) or null, ordered.
	 */
	public function generate( $kind = null ) {
		$kind = $kind ?: $this->pick( $this->kinds() );
		switch ( $kind ) {
			case 'site':
				$q = $this->pick( $this->site_questions() );
				return array( 'kind' => 'site', 'question' => $q['question'], 'answers' => $q['answers'], 'options' => array(), 'choices' => null, 'ordered' => false );

			case 'instruction':
				$ordinals = array(
					1 => _x( 'first', 'ordinal word in "Type the first word of this sentence."', 'quill-accessible-forms' ),
					2 => _x( 'second', 'ordinal word', 'quill-accessible-forms' ),
					3 => _x( 'third', 'ordinal word', 'quill-accessible-forms' ),
					4 => _x( 'fourth', 'ordinal word', 'quill-accessible-forms' ),
					5 => _x( 'fifth', 'ordinal word', 'quill-accessible-forms' ),
				);
				$style = $this->rand( 0, 2 );
				if ( 2 === $style ) {
					$objects = array_slice( $this->shuffled( self::choice_bank()['objects'] ), 0, 5 );
					$at      = $this->rand( 0, 3 );
					/* translators: 1: a word from the list, 2: the list. */
					$question = sprintf( __( 'Which word comes right after %1$s in this list: %2$s?', 'quill-accessible-forms' ), $objects[ $at ], self::list_text( $objects ) );
					return array( 'kind' => 'instruction', 'question' => $question, 'answers' => array( $objects[ $at + 1 ] ), 'options' => $objects, 'choices' => null, 'ordered' => false, 'literal' => true );
				}
				if ( 1 === $style ) {
					$question = __( 'Type the last word of this sentence.', 'quill-accessible-forms' );
					$words    = self::words_of( $question );
					return array( 'kind' => 'instruction', 'question' => $question, 'answers' => array( end( $words ) ), 'options' => array(), 'choices' => null, 'ordered' => false, 'literal' => true );
				}
				$n = $this->rand( 1, 5 );
				/* translators: %s: an ordinal word, such as "second". */
				$question = sprintf( __( 'Type the %s word of this sentence.', 'quill-accessible-forms' ), $ordinals[ $n ] );
				$words    = self::words_of( $question );
				return array( 'kind' => 'instruction', 'question' => $question, 'answers' => array( $words[ $n - 1 ] ), 'options' => array(), 'choices' => null, 'ordered' => false, 'literal' => true );

			case 'choice':
				$bank    = self::choice_bank();
				$group   = $this->pick( $bank['groups'] );
				$right   = $this->pick( $group['words'] );
				$others  = array_slice( $this->shuffled( $bank['objects'] ), 0, 2 );
				$choices = $this->shuffled( array_merge( array( $right ), $others ) );
				/* translators: 1: a group, such as "an animal", 2: the list of choices. */
				$question = sprintf( __( 'Which of these is %1$s: %2$s?', 'quill-accessible-forms' ), $group['name'], self::list_text( $choices ) );
				return array( 'kind' => 'choice', 'question' => $question, 'answers' => array( $right ), 'options' => $choices, 'choices' => $choices, 'ordered' => false );

			case 'order':
				$set   = $this->pick( self::order_bank() );
				$shown = $set['items'];
				for ( $i = 0; $i < 6 && $shown === $set['items']; $i++ ) {
					$shown = $this->shuffled( $set['items'] );
				}
				if ( $shown === $set['items'] ) {
					$shown = array_reverse( $set['items'] );
				}
				/* translators: 1: the order, such as "smallest first", 2: the things to put in order. */
				$question = sprintf( __( 'Put these in order, %1$s: %2$s.', 'quill-accessible-forms' ), $set['way'], self::list_text( $shown ) );
				return array( 'kind' => 'order', 'question' => $question, 'answers' => array( implode( ' ', $set['items'] ) ), 'options' => $set['items'], 'choices' => null, 'ordered' => true );

			case 'maths':
				$words = self::number_words();
				$a     = $this->rand( 1, 9 );
				$b     = $this->rand( 1, 9 );
				/* translators: 1 and 2: numbers written as words, such as "seven" and "two". */
				$question = sprintf( __( 'What is %1$s plus %2$s?', 'quill-accessible-forms' ), $words[ $a ], $words[ $b ] );
				return array( 'kind' => 'maths', 'question' => $question, 'answers' => array( (string) ( $a + $b ) ), 'options' => array(), 'choices' => null, 'ordered' => false );

			case 'sense':
			default:
				$p    = $this->pick( self::sense_bank() );
				$more = $this->pick( $p['more'] );
				$less = $this->pick( $p['less'] );
				$pair = $this->rand( 0, 1 ) ? array( $more, $less ) : array( $less, $more );
				return array( 'kind' => 'sense', 'question' => sprintf( $p['template'], $pair[0], $pair[1] ), 'answers' => array( $more ), 'options' => array( $more, $less ), 'choices' => null, 'ordered' => false );
		}
	}

	/**
	 * The words of a sentence as a person reads them, without punctuation.
	 *
	 * @param string $sentence Sentence.
	 * @return string[]
	 */
	public static function words_of( $sentence ) {
		$words = array();
		foreach ( preg_split( '/\s+/u', trim( (string) $sentence ) ) as $w ) {
			$w = preg_replace( '/^[^\p{L}\p{N}]+|[^\p{L}\p{N}]+$/u', '', $w );
			if ( '' !== $w ) {
				$words[] = $w;
			}
		}
		return $words;
	}

	// Loose matching.

	/**
	 * An answer as words to compare: lower case, no punctuation, numbers
	 * as digits, and without "a", "an" or "the".
	 *
	 * @param string $text           Text.
	 * @param bool   $strip_articles False where "the" may itself be the answer.
	 * @return string[] Tokens.
	 */
	public static function tokens( $text, $strip_articles = true ) {
		$text = function_exists( 'mb_strtolower' ) ? mb_strtolower( (string) $text, 'UTF-8' ) : strtolower( (string) $text );
		$text = trim( preg_replace( '/[^\p{L}\p{N}]+/u', ' ', $text ) );
		if ( '' === $text ) {
			return array();
		}
		$numbers  = array_flip( array_map( 'strtolower', self::number_words() ) );
		$articles = array_flip( preg_split( '/\s+/', strtolower( trim( _x( 'a an the', 'articles to ignore in answers, separated by spaces', 'quill-accessible-forms' ) ) ) ) );
		$out      = array();
		foreach ( explode( ' ', $text ) as $t ) {
			if ( $strip_articles && isset( $articles[ $t ] ) ) {
				continue;
			}
			$out[] = isset( $numbers[ $t ] ) ? (string) $numbers[ $t ] : ( ctype_digit( $t ) ? (string) (int) $t : $t );
		}
		return $out;
	}

	/**
	 * Whether two words are the same allowing a slip: one letter in a word of
	 * five or more, two in a word of nine or more. Numbers must be exact.
	 *
	 * @param string $a Word.
	 * @param string $b Expected word.
	 * @return bool
	 */
	private static function close( $a, $b ) {
		if ( $a === $b ) {
			return true;
		}
		if ( ctype_digit( $a ) || ctype_digit( $b ) ) {
			return false;
		}
		$len = function_exists( 'mb_strlen' ) ? mb_strlen( $b, 'UTF-8' ) : strlen( $b );
		$max = $len >= 9 ? 2 : ( $len >= 5 ? 1 : 0 );
		return $max > 0 && levenshtein( $a, $b ) <= $max;
	}

	private static function same_words( $given, $expected ) {
		if ( count( $given ) !== count( $expected ) || ! $expected ) {
			return false;
		}
		foreach ( $expected as $i => $word ) {
			if ( ! self::close( $given[ $i ], $word ) ) {
				return false;
			}
		}
		return true;
	}

	private static function contains_words( $given, $phrase ) {
		$n = count( $phrase );
		for ( $i = 0; $n && $i + $n <= count( $given ); $i++ ) {
			if ( self::same_words( array_slice( $given, $i, $n ), $phrase ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Whether an answer is right.
	 *
	 * @param string $given   What the person wrote or chose.
	 * @param array  $record  answers, options, ordered (from generate()).
	 * @return bool
	 */
	public static function matches( $given, $record ) {
		$strip = empty( $record['literal'] );
		$g     = self::tokens( $given, $strip );
		if ( ! $g ) {
			return false;
		}
		if ( ! empty( $record['ordered'] ) ) {
			$fillers = array_flip( self::tokens( _x( 'and then next', 'filler words to ignore in an answer that puts things in order, separated by spaces', 'quill-accessible-forms' ) ) );
			$g       = array_values( array_filter( $g, function ( $t ) use ( $fillers ) {
				return ! isset( $fillers[ $t ] );
			} ) );
		}
		foreach ( (array) $record['answers'] as $answer ) {
			if ( self::same_words( $g, self::tokens( $answer, $strip ) ) ) {
				return true;
			}
		}
		// "Snow is colder": the right option named, and no other option.
		if ( empty( $record['ordered'] ) && ! empty( $record['options'] ) ) {
			$named = array();
			foreach ( $record['options'] as $option ) {
				if ( self::contains_words( $g, self::tokens( $option, $strip ) ) ) {
					$named[] = $option;
				}
			}
			if ( 1 === count( $named ) && in_array( $named[0], (array) $record['answers'], true ) ) {
				return true;
			}
		}
		return false;
	}

	// The lifecycle: issue, verify once, count tries, Send Anyway.

	private function tries_key( QAF_Spam_Submission $s ) {
		return 'tries:' . $s->binding();
	}

	/**
	 * Wrong answers on this visit so far.
	 *
	 * @param QAF_Spam_Submission $s Submission.
	 * @return int
	 */
	public function tries( QAF_Spam_Submission $s ) {
		return (int) $this->store->get( $this->tries_key( $s ) );
	}

	/**
	 * A wrong answer: one more try on this visit, and one more failure for
	 * the address (longer proof of work, tighter rate limit).
	 *
	 * @param QAF_Spam_Submission $s Submission.
	 * @return int Tries on this visit.
	 */
	public function record_failure( QAF_Spam_Submission $s ) {
		$n = $this->tries( $s ) + 1;
		$this->store->set( $this->tries_key( $s ), $n, 3600 );
		if ( '' !== $s->ip ) {
			$this->failures->add( $s->ip );
		}
		return $n;
	}

	/**
	 * Ask a new question: the answer is kept here, the page gets the rest.
	 *
	 * @param QAF_Spam_Submission $s     Submission.
	 * @param string              $error What went wrong with the last answer, if anything.
	 * @return array id, kind, question, choices, attempt, error, send_anyway (an id, or null), html.
	 */
	public function issue( QAF_Spam_Submission $s, $error = '' ) {
		$q  = $this->generate();
		$id = bin2hex( random_bytes( 16 ) );
		$this->store->set( 'ch:' . $id, array(
			'answers' => $q['answers'],
			'options' => $q['options'],
			'ordered' => $q['ordered'],
			'literal' => ! empty( $q['literal'] ),
			'kind'    => $q['kind'],
			'form_id' => (int) $s->form_id,
			'binding' => $s->binding(),
			'expires' => $this->now() + self::TTL,
		), self::TTL + 3600 ); // Kept an hour past expiry, so "expired" can be told apart from "never issued".

		$tries       = $this->tries( $s );
		$send_anyway = null;
		$after = (int) $this->settings->get( 'send_anyway_after' );
		if ( $tries >= ( $after > 0 ? $after : self::SEND_ANYWAY_AFTER ) ) {
			$send_anyway = bin2hex( random_bytes( 16 ) );
			$this->store->set( 'sa:' . $send_anyway, array( 'form_id' => (int) $s->form_id, 'binding' => $s->binding(), 'tries' => $tries ), self::TTL + 3600 );
		}
		$this->count( 'asked' );

		$public = array(
			'id'          => $id,
			'kind'        => $q['kind'],
			'question'    => $q['question'],
			'choices'     => $q['choices'],
			'attempt'     => $tries + 1,
			'error'       => (string) $error,
			'send_anyway' => $send_anyway,
		);
		$public['html'] = self::render( $public );
		return $public;
	}

	/**
	 * Check an answer. A question works once: whatever the outcome, its id
	 * is spent.
	 *
	 * @param string              $id     Question id.
	 * @param mixed               $answer The answer.
	 * @param QAF_Spam_Submission $s      Submission.
	 * @return string pass, fail, empty, expired, or unknown (never issued, spent, or for another form or visit).
	 */
	public function verify( $id, $answer, QAF_Spam_Submission $s ) {
		if ( ! is_string( $id ) || ! preg_match( '/^[0-9a-f]{32}$/', $id ) ) {
			return 'unknown';
		}
		$record = $this->store->get( 'ch:' . $id );
		$this->store->delete( 'ch:' . $id );
		if ( ! is_array( $record ) || (int) $record['form_id'] !== (int) $s->form_id || ! hash_equals( $record['binding'], $s->binding() ) ) {
			return 'unknown';
		}
		if ( $record['expires'] < $this->now() ) {
			return 'expired';
		}
		$answer = is_scalar( $answer ) ? (string) $answer : '';
		if ( '' === trim( $answer ) ) {
			return 'empty';
		}
		if ( self::matches( $answer, $record ) ) {
			$this->count( 'passed' );
			return 'pass';
		}
		$this->count( 'failed' );
		return 'fail';
	}

	/**
	 * Use a Send Anyway id: once, on the visit it was offered to.
	 *
	 * @param string              $id Id.
	 * @param QAF_Spam_Submission $s  Submission.
	 * @return int|false The tries it was offered after, or false.
	 */
	public function redeem_send_anyway( $id, QAF_Spam_Submission $s ) {
		if ( ! is_string( $id ) || ! preg_match( '/^[0-9a-f]{32}$/', $id ) ) {
			return false;
		}
		$record = $this->store->get( 'sa:' . $id );
		$this->store->delete( 'sa:' . $id );
		if ( ! is_array( $record ) || (int) $record['form_id'] !== (int) $s->form_id || ! hash_equals( $record['binding'], $s->binding() ) ) {
			return false;
		}
		$this->count( 'sent_anyway' );
		return (int) $record['tries'];
	}

	// This week's numbers, for the Spam page in QAF and in QUILL's Workbench.

	private function week( $weeks_ago = 0 ) {
		return gmdate( 'o-\WW', $this->now() - $weeks_ago * 7 * DAY_IN_SECONDS );
	}

	private function count( $what ) {
		$key           = 'stats:' . $this->week();
		$stats         = $this->stats();
		$stats[ $what ] = ( isset( $stats[ $what ] ) ? $stats[ $what ] : 0 ) + 1;
		$this->store->set( $key, $stats, self::STATS_TTL_DAYS * DAY_IN_SECONDS );
	}

	/**
	 * This week: asked, passed, failed, sent_anyway.
	 *
	 * @return array
	 */
	public function stats( $weeks_ago = 0 ) {
		$week  = $this->week( $weeks_ago );
		$stats = $this->store->get( 'stats:' . $week );
		return array_merge( array( 'week' => $week, 'asked' => 0, 'passed' => 0, 'failed' => 0, 'sent_anyway' => 0 ), is_array( $stats ) ? $stats : array() );
	}

	/**
	 * This week and the ones before it, newest first. A week nobody was
	 * asked anything in is there with noughts, so the list has no gaps.
	 * Weeks older than STATS_TTL_DAYS are forgotten, and so are weeks a QAF
	 * older than this list counted (it kept each week for 15 days).
	 *
	 * @param int $weeks How many weeks, this one included (1 to HISTORY_MAX).
	 * @return array[] week, asked, passed, failed, sent_anyway.
	 */
	public function history( $weeks = 12 ) {
		$weeks = max( 1, min( self::HISTORY_MAX, (int) $weeks ) );
		$out   = array();
		for ( $i = 0; $i < $weeks; $i++ ) {
			$out[] = $this->stats( $i );
		}
		return $out;
	}

	// The question on the page.

	/**
	 * The question as a form field like any other: a real label, the
	 * question as its description (or, for a short list, the legend of
	 * radio buttons), the error beside it and announced politely, and no
	 * time limit on the person.
	 *
	 * @param array $c From issue().
	 * @return string HTML.
	 */
	public static function render( $c ) {
		$p        = 'qaf-challenge-' . substr( preg_replace( '/[^0-9a-f]/', '', (string) $c['id'] ), 0, 12 );
		$has_err  = '' !== (string) $c['error'];
		$invalid  = $has_err ? ' aria-invalid="true"' : '';
		$html     = '<div class="qaf-challenge" data-qaf-challenge="' . esc_attr( $c['id'] ) . '"' . ( $has_err ? ' data-qaf-error="' . esc_attr( $c['error'] ) . '"' : '' ) . '>';
		$html    .= '<p class="qaf-challenge-intro" id="' . esc_attr( $p ) . '-intro">' . esc_html__( 'One more question before this is sent. It helps the site tell people from automated spam, and there is no time limit.', 'quill-accessible-forms' ) . '</p>';

		if ( ! empty( $c['choices'] ) ) {
			$html .= '<fieldset class="qaf-challenge-field" aria-describedby="' . esc_attr( $p ) . '-error">';
			$html .= '<legend class="qaf-challenge-question" id="' . esc_attr( $p ) . '-question">' . esc_html( $c['question'] ) . '</legend>';
			foreach ( array_values( $c['choices'] ) as $i => $choice ) {
				$cid   = $p . '-c' . $i;
				$html .= '<div class="qaf-challenge-choice"><input type="radio" id="' . esc_attr( $cid ) . '" name="qaf_challenge_answer" value="' . esc_attr( $choice ) . '"' . $invalid . ' /> <label for="' . esc_attr( $cid ) . '">' . esc_html( $choice ) . '</label></div>';
			}
			$html .= '<div class="qaf-challenge-error" id="' . esc_attr( $p ) . '-error" aria-live="polite"></div>';
			$html .= '</fieldset>';
		} else {
			$html .= '<div class="qaf-challenge-field">';
			$html .= '<label class="qaf-label" for="' . esc_attr( $p ) . '-answer">' . esc_html__( 'Your answer', 'quill-accessible-forms' ) . '</label>';
			$html .= '<p class="qaf-challenge-question" id="' . esc_attr( $p ) . '-question">' . esc_html( $c['question'] ) . '</p>';
			$html .= '<input type="text" class="qaf-input" id="' . esc_attr( $p ) . '-answer" name="qaf_challenge_answer" autocomplete="off" autocapitalize="off" spellcheck="false" aria-describedby="' . esc_attr( $p ) . '-question ' . esc_attr( $p ) . '-error"' . $invalid . ' />';
			$html .= '<div class="qaf-challenge-error" id="' . esc_attr( $p ) . '-error" aria-live="polite"></div>';
			$html .= '</div>';
		}
		$html .= '<input type="hidden" name="qaf_challenge_id" value="' . esc_attr( $c['id'] ) . '" />';

		if ( ! empty( $c['send_anyway'] ) ) {
			$html .= '<div class="qaf-send-anyway">';
			$html .= '<input type="hidden" name="qaf_send_anyway" value="" />';
			$html .= '<p id="' . esc_attr( $p ) . '-anyway">' . esc_html__( 'Still stuck? You can send the form as it is. A person will read it before it is published or answered.', 'quill-accessible-forms' ) . '</p>';
			$html .= '<button type="button" class="qaf-send-anyway-button" data-qaf-send-anyway="' . esc_attr( $c['send_anyway'] ) . '" aria-describedby="' . esc_attr( $p ) . '-anyway">' . esc_html__( 'Send Anyway', 'quill-accessible-forms' ) . '</button>';
			$html .= '</div>';
		}
		return $html . '</div>';
	}
}

/**
 * The text question in the list of checks, so the settings page and the
 * Workbench show its state. The guard asks the question itself, after the
 * quiet checks, so this provider never gives a verdict.
 */
class QAF_Spam_Challenge_Check extends QAF_Spam_Provider_Base {

	public function id() {
		return 'challenge';
	}

	public function label() {
		return __( 'Text questions', 'quill-accessible-forms' );
	}

	public function availability() {
		$modes = array(
			QAF_Spam_Settings::ASK_WHEN_UNSURE => __( 'Asked only when the other checks are unsure.', 'quill-accessible-forms' ),
			QAF_Spam_Settings::ASK_ALWAYS      => __( 'Asked on every form.', 'quill-accessible-forms' ),
			QAF_Spam_Settings::ASK_NEVER       => __( 'Never asked; doubtful entries are held.', 'quill-accessible-forms' ),
		);
		$mode = (string) $this->settings->get( 'challenge_mode' );
		return array( 'available' => true, 'note' => isset( $modes[ $mode ] ) ? $modes[ $mode ] : '' );
	}

	public function check( QAF_Spam_Submission $s ) {
		return null;
	}
}
