/**
 * The BAF expression language, in JavaScript.
 *
 * A line-for-line port of includes/model/class-baf-expression.php, so a
 * published form decides what to show, require and calculate exactly as the
 * server does. The grammar and evaluation rules are in
 * docs/expression-language.md; tests/conformance/expressions/cases.json is
 * the contract, and tests/js/run-conformance.js runs it against this file.
 *
 * Where PHP and JavaScript differ, this file follows PHP:
 * - strings are compared, searched and lower-cased as PHP does on UTF-8
 *   bytes (only A-Z fold; ordering is by code point);
 * - numbers that PHP would hold as floats are written the way PHP writes
 *   them ("1.0", "1.0e+20");
 * - error offsets count UTF-8 bytes.
 *
 * Works in the browser (window.BAFExpression) and in Node (module.exports).
 * No dependencies.
 *
 * @package BAF
 * @since 1.1.0
 */
(function (root, factory) {
    'use strict';
    var api = factory();
    if (typeof module === 'object' && module.exports) {
        module.exports = api;
    } else {
        root.BAFExpression = api;
    }
}(typeof self !== 'undefined' ? self : this, function () {
    'use strict';

    var FUNCTIONS = {
        today: [0, 0], date: [1, 1], days_between: [2, 2], age: [1, 1], abs: [1, 1],
        round: [1, 2], floor: [1, 1], ceil: [1, 1], min: [1, -1], max: [1, -1],
        sum: [1, -1], avg: [1, -1], count: [1, 1], length: [1, 1], 'if': [3, 3],
        score: [1, 1], user: [1, 1], query: [1, 1], geo: [0, 0]
    };

    var RESERVED = [
        'and', 'or', 'not', 'is', 'contains', 'does', 'contain', 'starts', 'ends', 'with',
        'empty', 'filled', 'before', 'after', 'within', 'days', 'of', 'greater', 'less',
        'than', 'true', 'false', 'null', 'score', 'today', 'at', 'least', 'most'
    ];

    var PRECEDENCE = {
        or: 1, and: 2, not: 3,
        is: 4, is_not: 4, lt: 4, le: 4, gt: 4, ge: 4,
        one_of: 4, not_one_of: 4, empty: 4, not_empty: 4,
        contains: 4, not_contains: 4, starts_with: 4, ends_with: 4,
        before: 4, after: 4, within: 4,
        add: 5, sub: 5, mul: 6, div: 6, mod: 6, neg: 7
    };

    /**
     * An expression the grammar does not accept. offset is in UTF-8 bytes.
     */
    function ExpressionError(message, offset) {
        this.name = 'ExpressionError';
        this.message = message;
        this.offset = offset || 0;
        this.stack = (new Error(message)).stack;
    }
    ExpressionError.prototype = Object.create(Error.prototype);
    ExpressionError.prototype.constructor = ExpressionError;

    // ------------------------------------------------------------------
    // PHP-compatible helpers
    // ------------------------------------------------------------------

    function own(obj, key) {
        return obj !== null && typeof obj === 'object' && Object.prototype.hasOwnProperty.call(obj, key);
    }

    /** PHP's ?? on an array key: null when absent or null. */
    function get(obj, key) {
        if (!own(obj, key)) {
            return null;
        }
        var v = obj[key];
        return v === undefined ? null : v;
    }

    /** A PHP array: a JS array or a plain object. */
    function isArr(v) {
        return v !== null && typeof v === 'object';
    }

    function values(v) {
        return Array.isArray(v) ? v : Object.keys(v).map(function (k) { return v[k]; });
    }

    function isNum(v) {
        return typeof v === 'number';
    }

    function isDigit(c) {
        return c >= '0' && c <= '9';
    }

    function isAlpha(c) {
        return (c >= 'a' && c <= 'z') || (c >= 'A' && c <= 'Z');
    }

    function isAlnum(c) {
        return isDigit(c) || isAlpha(c);
    }

    function isXdigits(s) {
        return s.length > 0 && /^[0-9A-Fa-f]+$/.test(s);
    }

    /** PHP strtolower: A-Z only. */
    function lower(s) {
        return s.replace(/[A-Z]+/g, function (m) { return m.toLowerCase(); });
    }

    /** PHP trim(): " \t\n\r\0\x0B". */
    function phpTrim(s) {
        return s.replace(/^[ \t\n\r\0\x0B]+|[ \t\n\r\0\x0B]+$/g, '');
    }

    function utf8Length(s) {
        var n = 0;
        for (var i = 0; i < s.length; i++) {
            var c = s.charCodeAt(i);
            if (c < 0x80) {
                n += 1;
            } else if (c < 0x800) {
                n += 2;
            } else if (c >= 0xD800 && c <= 0xDBFF && i + 1 < s.length) {
                var d = s.charCodeAt(i + 1);
                if (d >= 0xDC00 && d <= 0xDFFF) {
                    n += 4;
                    i++;
                } else {
                    n += 3;
                }
            } else {
                n += 3;
            }
        }
        return n;
    }

    /** Order of two strings by UTF-8 bytes, which is order by code point. */
    function byteCompare(a, b) {
        var ia = 0;
        var ib = 0;
        while (ia < a.length && ib < b.length) {
            var ca = a.codePointAt(ia);
            var cb = b.codePointAt(ib);
            if (ca !== cb) {
                return ca < cb ? -1 : 1;
            }
            ia += ca > 0xFFFF ? 2 : 1;
            ib += cb > 0xFFFF ? 2 : 1;
        }
        if (ia < a.length) {
            return 1;
        }
        return ib < b.length ? -1 : 0;
    }

    /**
     * A float the way PHP's json_encode writes it (serialize_precision -1):
     * fixed notation while the decimal point is within 17 digits and the
     * number is at least 0.0001, otherwise "d.ddde+N".
     */
    function phpFloat(v, preserveZero) {
        if (!isFinite(v)) {
            return '0';
        }
        if (v === 0) {
            var z = (Object.is(v, -0) ? '-0' : '0');
            return preserveZero ? z + '.0' : z;
        }
        var exp = v.toExponential();
        var m = /^(-?)(\d)(?:\.(\d+))?e([+-])(\d+)$/.exec(exp);
        var decpt = parseInt(m[5], 10) * (m[4] === '-' ? -1 : 1) + 1;
        if (decpt < -3 || decpt > 17) {
            return m[1] + m[2] + '.' + (m[3] || '0') + 'e' + m[4] + m[5];
        }
        var text = String(v);
        if (/e/.test(text)) {
            // JavaScript switches to exponent form earlier than PHP below 1e-6.
            var digits = m[2] + (m[3] || '');
            if (decpt <= 0) {
                text = m[1] + '0.' + new Array(1 - decpt).join('0') + digits;
            } else {
                while (digits.length < decpt) {
                    digits += '0';
                }
                text = m[1] + digits.slice(0, decpt) + (digits.length > decpt ? '.' + digits.slice(decpt) : '');
            }
        }
        if (preserveZero && text.indexOf('.') === -1) {
            text += '.0';
        }
        return text;
    }

    /**
     * Whether a JS number stands for a PHP integer. JavaScript has one number
     * type; an integral value within PHP's integer range is taken to be one,
     * which is what every answer and every integer calculation gives. (A PHP
     * float of 1e17 or more would print as "1.0e+17"; no form reaches that.)
     */
    function looksInt(v) {
        return Number.isInteger(v) && Math.abs(v) < 9.2e18 && !Object.is(v, -0);
    }

    /**
     * A number as plain decimal digits, never in exponent form, so canonical
     * text reads back (BAF_Expression::number_text).
     */
    function numberText(v, floatMark) {
        var text = phpFloat(v, floatMark);
        var m = /^(-?)(\d)(?:\.(\d+))?e([+-]\d+)$/i.exec(text);
        if (!m) {
            return text;
        }
        var digits = (m[2] + (m[3] || '')).replace(/0+$/, '');
        var point = parseInt(m[4], 10) + 1;
        var out;
        if (point <= 0) {
            out = '0.' + new Array(1 - point).join('0') + digits;
        } else {
            while (digits.length < point) {
                digits += '0';
            }
            var frac = digits.slice(point);
            out = digits.slice(0, point) + (frac !== '' ? '.' + frac : (floatMark ? '.0' : ''));
        }
        return m[1] + out;
    }

    // ------------------------------------------------------------------
    // Tokenizer
    // ------------------------------------------------------------------

    function tokenize(s) {
        var tokens = [];
        var n = s.length;
        var i = 0;
        while (i < n) {
            var c = s.charAt(i);
            if (c === ' ' || c === '\t' || c === '\n' || c === '\r') {
                i++;
                continue;
            }
            var start = i;
            if (c === '#' || c === '{') {
                var close = c === '{';
                i++;
                var j = i;
                while (j < n && (isAlnum(s.charAt(j)) || s.charAt(j) === '_')) {
                    j++;
                }
                if (j === i) {
                    throw new ExpressionError('A field reference needs a field ID after "' + c + '".', start);
                }
                var id = s.slice(i, j);
                i = j;
                if (close) {
                    if (i >= n || s.charAt(i) !== '}') {
                        throw new ExpressionError('A field reference that starts with "{" must end with "}".', start);
                    }
                    i++;
                }
                tokens.push({ type: 'ref', text: id, offset: start });
                continue;
            }
            if (isDigit(c) || (c === '.' && i + 1 < n && isDigit(s.charAt(i + 1)))) {
                var k = i;
                while (k < n && isDigit(s.charAt(k))) {
                    k++;
                }
                if (k < n && s.charAt(k) === '.' && k + 1 < n && isDigit(s.charAt(k + 1))) {
                    k++;
                    while (k < n && isDigit(s.charAt(k))) {
                        k++;
                    }
                }
                tokens.push({ type: 'number', text: s.slice(i, k), offset: start });
                i = k;
                continue;
            }
            if (c === '"' || c === "'") {
                var scanned = scanString(s, i);
                tokens.push({ type: 'string', text: scanned[0], offset: start });
                i = scanned[1];
                continue;
            }
            if (isAlpha(c) || c === '_') {
                var w = i;
                while (w < n && (isAlnum(s.charAt(w)) || s.charAt(w) === '_')) {
                    w++;
                }
                tokens.push({ type: 'word', text: s.slice(i, w), offset: start });
                i = w;
                continue;
            }
            var two = s.substr(i, 2);
            if (['==', '!=', '<>', '<=', '>='].indexOf(two) !== -1) {
                tokens.push({ type: 'punct', text: two, offset: start });
                i += 2;
                continue;
            }
            if ('()[],+-*/%=<>'.indexOf(c) !== -1) {
                tokens.push({ type: 'punct', text: c, offset: start });
                i++;
                continue;
            }
            throw new ExpressionError('The character "' + String.fromCodePoint(s.codePointAt(i)) + '" is not part of the expression language.', start);
        }
        tokens.push({ type: 'eof', text: '', offset: n });
        return tokens;
    }

    /** A quoted string. Escapes: \n \t \r \\ \" \' \/ \uXXXX. */
    function scanString(s, i) {
        var quote = s.charAt(i);
        var n = s.length;
        var start = i;
        i++;
        var out = '';
        while (i < n && s.charAt(i) !== quote) {
            var c = s.charAt(i);
            if (c === '\\' && i + 1 < n) {
                var e = s.charAt(i + 1);
                i += 2;
                if (e === 'n') {
                    out += '\n';
                } else if (e === 't') {
                    out += '\t';
                } else if (e === 'r') {
                    out += '\r';
                } else if (e === 'u') {
                    var hex = s.substr(i, 4);
                    if (hex.length === 4 && isXdigits(hex)) {
                        var cp = parseInt(hex, 16);
                        i += 4;
                        if (cp >= 0xD800 && cp <= 0xDBFF && s.substr(i, 2) === '\\u' && isXdigits(s.substr(i + 2, 4))) {
                            var lo = parseInt(s.substr(i + 2, 4), 16);
                            if (lo >= 0xDC00 && lo <= 0xDFFF) {
                                cp = 0x10000 + ((cp - 0xD800) << 10) + (lo - 0xDC00);
                                i += 6;
                            }
                        }
                        out += cp > 0xFFFF ? String.fromCodePoint(cp) : String.fromCharCode(cp);
                    } else {
                        out += 'u';
                    }
                } else {
                    out += e;
                }
                continue;
            }
            out += c;
            i++;
        }
        if (i >= n) {
            throw new ExpressionError('A quoted value is not closed.', start);
        }
        return [out, i + 1];
    }

    // ------------------------------------------------------------------
    // Parser
    // ------------------------------------------------------------------

    function op(name, args) {
        return { type: 'op', op: name, args: args };
    }

    /** A number literal; float marks what PHP would hold as a float. */
    function numberLiteral(value, isFloat) {
        var node = { type: 'literal', value: value };
        if (isFloat) {
            Object.defineProperty(node, 'float', { value: true, enumerable: false });
        }
        return node;
    }

    function isFloatLiteral(node) {
        return node.float === true;
    }

    function Parser(tokens) {
        this.tokens = tokens;
        this.pos = 0;
    }

    Parser.prototype.peek = function (ahead) {
        var i = Math.min(this.pos + (ahead || 0), this.tokens.length - 1);
        return this.tokens[i];
    };
    Parser.prototype.peekType = function (ahead) {
        return this.peek(ahead).type;
    };
    Parser.prototype.isWord = function (word, ahead) {
        var t = this.peek(ahead);
        return t.type === 'word' && lower(t.text) === word;
    };
    Parser.prototype.isPunct = function (p, ahead) {
        var t = this.peek(ahead);
        return t.type === 'punct' && t.text === p;
    };
    Parser.prototype.next = function () {
        var t = this.peek();
        if (t.type !== 'eof') {
            this.pos++;
        }
        return t;
    };
    Parser.prototype.found = function (t) {
        return t.type === 'eof' ? 'the end' : '"' + t.text + '"';
    };
    Parser.prototype.expectWord = function (word) {
        if (!this.isWord(word)) {
            var t = this.peek();
            throw new ExpressionError('Expected "' + word + '" but found ' + this.found(t) + '.', t.offset);
        }
        this.next();
    };
    Parser.prototype.expectPunct = function (p) {
        if (!this.isPunct(p)) {
            var t = this.peek();
            throw new ExpressionError('Expected "' + p + '" but found ' + this.found(t) + '.', t.offset);
        }
        this.next();
    };

    function flatten(name, args) {
        var flat = [];
        args.forEach(function (a) {
            if (a.type === 'op' && a.op === name) {
                a.args.forEach(function (b) { flat.push(b); });
            } else {
                flat.push(a);
            }
        });
        return op(name, flat);
    }

    Parser.prototype.parseOr = function () {
        var args = [this.parseAnd()];
        while (this.isWord('or')) {
            this.next();
            args.push(this.parseAnd());
        }
        return args.length === 1 ? args[0] : flatten('or', args);
    };

    Parser.prototype.parseAnd = function () {
        var args = [this.parseNot()];
        while (this.isWord('and')) {
            this.next();
            args.push(this.parseNot());
        }
        return args.length === 1 ? args[0] : flatten('and', args);
    };

    Parser.prototype.parseNot = function () {
        if (this.isWord('not')) {
            this.next();
            return op('not', [this.parseNot()]);
        }
        return this.parseComparison();
    };

    Parser.prototype.parseComparison = function () {
        var left = this.parseAdditive();
        var t = this.peek();
        if (t.type === 'punct') {
            var map = { '=': 'is', '==': 'is', '!=': 'is_not', '<>': 'is_not', '<': 'lt', '<=': 'le', '>': 'gt', '>=': 'ge' };
            if (own(map, t.text)) {
                this.next();
                return op(map[t.text], [left, this.parseAdditive()]);
            }
            return left;
        }
        if (t.type !== 'word') {
            return left;
        }
        var w = lower(t.text);
        if (w === 'is') {
            this.next();
            var neg = false;
            if (this.isWord('not')) {
                this.next();
                neg = true;
            }
            if (this.isWord('one') && this.isWord('of', 1)) {
                this.next();
                this.next();
                return op(neg ? 'not_one_of' : 'one_of', [left, this.parseList()]);
            }
            if (this.isWord('empty')) {
                this.next();
                return op(neg ? 'not_empty' : 'empty', [left]);
            }
            if (this.isWord('filled')) {
                this.next();
                if (this.isWord('in')) {
                    this.next();
                }
                return op(neg ? 'empty' : 'not_empty', [left]);
            }
            if (!neg) {
                var words = [['greater', 'gt'], ['less', 'lt']];
                for (var i = 0; i < words.length; i++) {
                    if (this.isWord(words[i][0])) {
                        this.next();
                        if (this.isWord('than')) {
                            this.next();
                        }
                        return op(words[i][1], [left, this.parseAdditive()]);
                    }
                }
                if (this.isWord('at') && (this.isWord('least', 1) || this.isWord('most', 1))) {
                    this.next();
                    var atOp = this.isWord('least') ? 'ge' : 'le';
                    this.next();
                    return op(atOp, [left, this.parseAdditive()]);
                }
                if (this.isWord('before') || this.isWord('after')) {
                    var ba = lower(this.next().text);
                    return op(ba, [left, this.parseAdditive()]);
                }
                if (this.isWord('within')) {
                    this.next();
                    return this.parseWithin(left);
                }
            }
            return op(neg ? 'is_not' : 'is', [left, this.parseAdditive()]);
        }
        switch (w) {
            case 'empty':
                this.next();
                return op('empty', [left]);
            case 'filled':
                this.next();
                return op('not_empty', [left]);
            case 'contains':
                this.next();
                return op('contains', [left, this.parseAdditive()]);
            case 'does':
                this.next();
                this.expectWord('not');
                this.expectWord('contain');
                return op('not_contains', [left, this.parseAdditive()]);
            case 'starts':
            case 'ends':
                this.next();
                if (this.isWord('with')) {
                    this.next();
                }
                return op(w === 'starts' ? 'starts_with' : 'ends_with', [left, this.parseAdditive()]);
            case 'greater':
            case 'less':
                this.next();
                if (this.isWord('than')) {
                    this.next();
                }
                return op(w === 'greater' ? 'gt' : 'lt', [left, this.parseAdditive()]);
            case 'before':
            case 'after':
                this.next();
                return op(w, [left, this.parseAdditive()]);
            case 'within':
                this.next();
                return this.parseWithin(left);
        }
        return left;
    };

    Parser.prototype.parseWithin = function (left) {
        var n = this.parseAdditive();
        this.expectWord('days');
        var args = [left, n];
        if (this.isWord('of')) {
            this.next();
            args.push(this.parseAdditive());
        }
        return op('within', args);
    };

    Parser.prototype.parseList = function () {
        var start = this.peek();
        this.expectPunct('[');
        var items = [];
        if (!this.isPunct(']')) {
            items.push(this.parseAdditive());
            while (this.isPunct(',')) {
                this.next();
                items.push(this.parseAdditive());
            }
        }
        if (!this.isPunct(']')) {
            throw new ExpressionError('A list must end with "]".', start.offset);
        }
        this.next();
        return { type: 'list', items: items };
    };

    Parser.prototype.parseAdditive = function () {
        var left = this.parseMultiplicative();
        while (this.isPunct('+') || this.isPunct('-')) {
            var name = this.next().text === '+' ? 'add' : 'sub';
            left = op(name, [left, this.parseMultiplicative()]);
        }
        return left;
    };

    Parser.prototype.parseMultiplicative = function () {
        var left = this.parseUnary();
        while (this.isPunct('*') || this.isPunct('/') || this.isPunct('%')) {
            var p = this.next().text;
            var name = p === '*' ? 'mul' : (p === '/' ? 'div' : 'mod');
            left = op(name, [left, this.parseUnary()]);
        }
        return left;
    };

    Parser.prototype.parseUnary = function () {
        if (this.isPunct('-')) {
            this.next();
            var operand = this.parseUnary();
            if (operand.type === 'literal' && isNum(operand.value)) {
                return numberLiteral(isFloatLiteral(operand) ? -operand.value : (0 - operand.value), isFloatLiteral(operand));
            }
            return op('neg', [operand]);
        }
        return this.parsePrimary();
    };

    function toNumber(text) {
        if (text.indexOf('.') === -1 && text.replace(/^0+/, '').length < 16) {
            return numberLiteral(parseInt(text, 10), false);
        }
        return numberLiteral(parseFloat(text), true);
    }

    Parser.prototype.parsePrimary = function () {
        var t = this.peek();
        switch (t.type) {
            case 'number':
                this.next();
                return toNumber(t.text);
            case 'string':
                this.next();
                return { type: 'literal', value: t.text };
            case 'ref':
                this.next();
                return { type: 'ref', field: t.text };
            case 'punct':
                if (t.text === '(') {
                    this.next();
                    var inner = this.parseOr();
                    this.expectPunct(')');
                    return inner;
                }
                if (t.text === '[') {
                    return this.parseList();
                }
                throw new ExpressionError('Unexpected "' + t.text + '".', t.offset);
            case 'word':
                var w = lower(t.text);
                if (this.isPunct('(', 1)) {
                    if (!own(FUNCTIONS, w)) {
                        throw new ExpressionError('There is no function called "' + t.text + '".', t.offset);
                    }
                    this.next();
                    this.next();
                    var args = [];
                    if (!this.isPunct(')')) {
                        args.push(this.parseOr());
                        while (this.isPunct(',')) {
                            this.next();
                            args.push(this.parseOr());
                        }
                    }
                    this.expectPunct(')');
                    var min = FUNCTIONS[w][0];
                    var max = FUNCTIONS[w][1];
                    if (args.length < min || (max >= 0 && args.length > max)) {
                        throw new ExpressionError('The function "' + w + '" was given ' + args.length + ' value(s).', t.offset);
                    }
                    return { type: 'call', name: w, args: args };
                }
                if (w === 'true' || w === 'false') {
                    this.next();
                    return { type: 'literal', value: w === 'true' };
                }
                if (w === 'null') {
                    this.next();
                    return { type: 'literal', value: null };
                }
                if (w === 'today') {
                    this.next();
                    return { type: 'call', name: 'today', args: [] };
                }
                if (w === 'score') {
                    this.next();
                    return { type: 'var', name: 'score' };
                }
                if (RESERVED.indexOf(w) !== -1) {
                    throw new ExpressionError('Expected a value but found "' + t.text + '".', t.offset);
                }
                this.next();
                return { type: 'literal', value: t.text };
            case 'eof':
                throw new ExpressionError('The expression ends too early.', t.offset);
        }
        throw new ExpressionError('Unexpected "' + t.text + '".', t.offset);
    };

    /**
     * Parse expression text into a tree.
     * @throws ExpressionError
     */
    function parse(text) {
        var s = String(text === null || text === undefined ? '' : text);
        try {
            var p = new Parser(tokenize(s));
            if (p.peekType() === 'eof') {
                throw new ExpressionError('The expression is empty.', 0);
            }
            var node = p.parseOr();
            if (p.peekType() !== 'eof') {
                var t = p.peek();
                throw new ExpressionError('Unexpected "' + t.text + '".', t.offset);
            }
            return node;
        } catch (e) {
            if (e instanceof ExpressionError) {
                e.offset = utf8Length(s.slice(0, e.offset));
            }
            throw e;
        }
    }

    /** Null when the text is valid, otherwise the error message. */
    function check(text) {
        try {
            parse(text);
            return null;
        } catch (e) {
            if (e instanceof ExpressionError) {
                return e.message;
            }
            throw e;
        }
    }

    function refs(node) {
        var out = [];
        (function collect(n) {
            if (!isArr(n)) {
                return;
            }
            if (n.type === 'ref') {
                if (out.indexOf(n.field) === -1) {
                    out.push(n.field);
                }
                return;
            }
            ['args', 'items'].forEach(function (k) {
                if (Array.isArray(n[k])) {
                    n[k].forEach(collect);
                }
            });
        }(node));
        return out;
    }

    // ------------------------------------------------------------------
    // Canonical text
    // ------------------------------------------------------------------

    /** JSON, with non-ASCII and slashes as they are (PHP's flags). */
    function quote(s) {
        var clean = String(s).replace(/[\uD800-\uDBFF](?![\uDC00-\uDFFF])|(^|[^\uD800-\uDBFF])[\uDC00-\uDFFF]/g, function (m, before) {
            return (before || '') + '�';
        });
        return JSON.stringify(clean);
    }

    function literalText(v, isFloat) {
        if (v === null) {
            return 'null';
        }
        if (typeof v === 'boolean') {
            return v ? 'true' : 'false';
        }
        if (isNum(v)) {
            return isFloat || !looksInt(v) ? numberText(v, true) : String(v);
        }
        return quote(v);
    }

    function prec(node) {
        if (node.type === 'op') {
            return PRECEDENCE[node.op];
        }
        if (node.type === 'literal' && isNum(node.value) && (node.value < 0 || Object.is(node.value, -0))) {
            return 7;
        }
        return 8;
    }

    function child(node, min) {
        var text = toText(node);
        return prec(node) < min ? '(' + text + ')' : text;
    }

    var INFIX = {
        is: 'is', is_not: 'is not', lt: '<', le: '<=', gt: '>', ge: '>=',
        contains: 'contains', not_contains: 'does not contain',
        starts_with: 'starts with', ends_with: 'ends with',
        before: 'is before', after: 'is after',
        add: '+', sub: '-', mul: '*', div: '/', mod: '%'
    };

    function toText(node) {
        switch (node.type) {
            case 'literal':
                return literalText(node.value, isFloatLiteral(node));
            case 'ref':
                return '#' + node.field;
            case 'var':
                return node.name;
            case 'list':
                return '[' + node.items.map(function (n) { return child(n, 5); }).join(', ') + ']';
            case 'call':
                return node.name + '(' + node.args.map(toText).join(', ') + ')';
        }
        var a = node.args;
        var name = node.op;
        switch (name) {
            case 'or':
            case 'and':
                var p = PRECEDENCE[name];
                return a.map(function (n) { return child(n, p + 1); }).join(' ' + name + ' ');
            case 'not':
                return 'not ' + child(a[0], 3);
            case 'neg':
                return '-' + child(a[0], 7);
            case 'empty':
                return child(a[0], 5) + ' is empty';
            case 'not_empty':
                return child(a[0], 5) + ' is not empty';
            case 'one_of':
            case 'not_one_of':
                return child(a[0], 5) + (name === 'one_of' ? ' is one of ' : ' is not one of ') + toText(a[1]);
            case 'within':
                var text = child(a[0], 5) + ' is within ' + child(a[1], 5) + ' days';
                if (a.length > 2) {
                    text += ' of ' + child(a[2], 5);
                }
                return text;
        }
        var pr = PRECEDENCE[name];
        if (pr === 4) {
            return child(a[0], 5) + ' ' + INFIX[name] + ' ' + child(a[1], 5);
        }
        return child(a[0], pr) + ' ' + INFIX[name] + ' ' + child(a[1], pr + 1);
    }

    function canonical(text) {
        return toText(parse(text));
    }

    // ------------------------------------------------------------------
    // Sentences
    // ------------------------------------------------------------------

    var CALL_NAMES = {
        sum: 'the total of', avg: 'the average of', min: 'the smallest of', max: 'the largest of',
        round: 'rounded', floor: 'rounded down', ceil: 'rounded up', abs: 'the size of',
        age: 'the age in years from', date: 'the date'
    };

    var SENTENCE_WORDS = {
        is: 'is', is_not: 'is not', lt: 'is less than', le: 'is at most',
        gt: 'is greater than', ge: 'is at least', contains: 'contains',
        not_contains: 'does not contain', starts_with: 'starts with', ends_with: 'ends with',
        before: 'is before', after: 'is after',
        add: 'plus', sub: 'minus', mul: 'times', div: 'divided by', mod: 'remainder after dividing by'
    };

    var INVERSES = {
        is: 'is_not', is_not: 'is', empty: 'not_empty', not_empty: 'empty',
        one_of: 'not_one_of', not_one_of: 'one_of', contains: 'not_contains', not_contains: 'contains'
    };

    function toSentence(node, labels, choices) {
        return sentence(node, labels || {}, choices || {}, null);
    }

    function sentence(node, labels, choices, context) {
        var s = function (n, ctx) {
            return sentence(n, labels, choices, ctx === undefined ? null : ctx);
        };
        var g = function (n) {
            var text = s(n);
            return (n.type === 'op' && (n.op === 'and' || n.op === 'or')) ? '(' + text + ')' : text;
        };
        var parts;
        var last;
        switch (node.type) {
            case 'literal':
                var v = node.value;
                if (v === null) {
                    return 'nothing';
                }
                if (typeof v === 'boolean') {
                    return v ? 'yes' : 'no';
                }
                if (context !== null && own(choices, context) && typeof v === 'string' && get(choices[context], v) !== null) {
                    return String(choices[context][v]);
                }
                return typeof v === 'string' ? v : (isFloatLiteral(node) || !looksInt(v) ? numberText(v, false) : String(v));
            case 'ref':
                var label = get(labels, node.field);
                return label !== null && label !== '' ? String(label) : node.field;
            case 'var':
                return 'the score';
            case 'list':
                var items = node.items.map(function (n) { return s(n, context); });
                if (items.length <= 1) {
                    return items.join('');
                }
                last = items.pop();
                return items.join(', ') + ' or ' + last;
            case 'call':
                var args = node.args;
                switch (node.name) {
                    case 'today':
                        return 'today';
                    case 'geo':
                        return 'the visitor\'s location';
                    case 'score':
                        return 'the score for ' + s(args[0]);
                    case 'count':
                        return 'the number of choices in ' + s(args[0]);
                    case 'length':
                        return 'the length of ' + s(args[0]);
                    case 'user':
                        return 'the signed-in user\'s ' + str(nodeValue(args[0])).replace(/_/g, ' ');
                    case 'query':
                        return 'the link\'s ' + str(nodeValue(args[0])) + ' value';
                    case 'if':
                        return s(args[1]) + ' if ' + s(args[0]) + ', otherwise ' + s(args[2]);
                    case 'days_between':
                        return 'the days from ' + s(args[0]) + ' to ' + s(args[1]);
                }
                parts = args.map(function (n) { return s(n); });
                if (node.name === 'round' && parts.length === 2) {
                    return parts[0] + ' rounded to ' + parts[1] + ' places';
                }
                if (['round', 'floor', 'ceil'].indexOf(node.name) !== -1) {
                    return parts[0] + ' ' + CALL_NAMES[node.name];
                }
                last = parts.pop();
                return CALL_NAMES[node.name] + ' ' + (parts.length ? parts.join(', ') + ' and ' : '') + last;
        }
        var a = node.args;
        var name = node.op;
        var ctx = a[0] && a[0].type === 'ref' ? a[0].field : null;
        switch (name) {
            case 'or':
            case 'and':
                parts = a.map(g);
                last = parts.pop();
                return parts.join(', ') + (parts.length > 1 ? ',' : '') + ' ' + name + ' ' + last;
            case 'not':
                var inner = a[0];
                if (inner.type === 'op' && own(INVERSES, inner.op)) {
                    return s({ type: 'op', op: INVERSES[inner.op], args: inner.args });
                }
                if (inner.type === 'op') {
                    return 'not (' + s(inner) + ')';
                }
                return 'not ' + s(inner);
            case 'neg':
                return 'minus ' + s(a[0]);
            case 'empty':
                return s(a[0]) + ' is empty';
            case 'not_empty':
                return s(a[0]) + ' is filled in';
            case 'one_of':
                return s(a[0]) + ' is one of ' + s(a[1], ctx);
            case 'not_one_of':
                return s(a[0]) + ' is not one of ' + s(a[1], ctx);
            case 'within':
                return s(a[0]) + ' is within ' + s(a[1]) + ' days of ' + (a.length > 2 ? s(a[2]) : 'today');
        }
        var left = s(a[0]);
        var right = s(a[1], ctx);
        if (PRECEDENCE[name] >= 5) {
            if (a[0].type === 'op' && PRECEDENCE[a[0].op] < PRECEDENCE[name]) {
                left = '(' + left + ')';
            }
            if (a[1].type === 'op' && PRECEDENCE[a[1].op] <= PRECEDENCE[name]) {
                right = '(' + right + ')';
            }
        }
        return left + ' ' + SENTENCE_WORDS[name] + ' ' + right;
    }

    /** $node['value'] ?? '' as text. */
    function nodeValue(n) {
        var v = get(n, 'value');
        return v === null ? '' : v;
    }

    // ------------------------------------------------------------------
    // Evaluation
    // ------------------------------------------------------------------

    function truthy(v) {
        if (v === null || v === undefined || v === false || v === '') {
            return false;
        }
        if (isArr(v) && values(v).length === 0) {
            return false;
        }
        if (isNum(v)) {
            return v !== 0;
        }
        return true;
    }

    function isEmpty(v) {
        if (v === null || v === undefined) {
            return true;
        }
        if (v === false) {
            return false;
        }
        if (isArr(v)) {
            return values(v).every(isEmpty);
        }
        if (typeof v === 'string') {
            return phpTrim(v) === '';
        }
        return false;
    }

    function str(v) {
        if (v === null || v === undefined) {
            return '';
        }
        if (typeof v === 'boolean') {
            return v ? 'true' : 'false';
        }
        if (isArr(v)) {
            return values(v).map(str).join(', ');
        }
        if (isNum(v)) {
            return looksInt(v) ? String(v) : phpFloat(v, false);
        }
        return String(v);
    }

    var NUMERIC = /^[ \t\n\x0B\f\r]*-?(\d+(\.\d*)?|\.\d+)[ \t\n\x0B\f\r]*$/;

    function isNumericValue(v) {
        if (isNum(v)) {
            return true;
        }
        return typeof v === 'string' && NUMERIC.test(v);
    }

    function num(v) {
        if (isNum(v)) {
            return v;
        }
        if (v === true) {
            return 1;
        }
        if (isArr(v)) {
            return values(v).reduce(function (t, x) { return t + num(x); }, 0);
        }
        if (isNumericValue(v)) {
            var n = Number(v.replace(/^[ \t\n\x0B\f\r]+|[ \t\n\x0B\f\r]+$/g, ''));
            return Object.is(n, -0) && v.indexOf('.') === -1 ? 0 : n;
        }
        return 0;
    }

    function scalarEquals(a, b) {
        if (a === null || a === undefined) {
            a = '';
        }
        if (b === null || b === undefined) {
            b = '';
        }
        if (isNumericValue(a) && isNumericValue(b)) {
            return num(a) === num(b);
        }
        return str(a) === str(b);
    }

    function contains(a, b) {
        if (isArr(a)) {
            return values(a).some(function (x) { return scalarEquals(x, b); });
        }
        var needle = lower(str(b));
        return needle === '' || lower(str(a)).indexOf(needle) !== -1;
    }

    function equals(a, b) {
        if (isArr(a) && isArr(b)) {
            var av = values(a);
            var bv = values(b);
            if (av.length !== bv.length) {
                return false;
            }
            return bv.every(function (x) { return contains(av, x); });
        }
        if (isArr(a)) {
            return contains(a, b);
        }
        return scalarEquals(a, b);
    }

    function sign(x, y) {
        return x < y ? -1 : (x > y ? 1 : 0);
    }

    function compare(a, b) {
        if (isEmpty(a) || isEmpty(b)) {
            return null;
        }
        if (isNumericValue(a) && isNumericValue(b)) {
            return sign(num(a), num(b));
        }
        var da = dayNumber(a);
        var db = dayNumber(b);
        if (da !== null && db !== null) {
            return sign(da, db);
        }
        if (isArr(a) || isArr(b)) {
            return sign(num(a), num(b));
        }
        return byteCompare(str(a), str(b));
    }

    function normalizeNumber(n) {
        return n;
    }

    /** PHP round(): half away from zero, after PHP's pre-rounding. */
    function phpRound(value, places) {
        if (!isFinite(value)) {
            return value;
        }
        places = places || 0;
        var f = Math.pow(10, Math.abs(places));
        var x = places >= 0 ? value * f : value / f;
        if (!isFinite(x)) {
            return value;
        }
        x = Number(x.toPrecision(15));
        var r = x >= 0 ? Math.floor(x + 0.5) : Math.ceil(x - 0.5);
        var out = places >= 0 ? r / f : r * f;
        if (places > 0) {
            var fixed = Number(out.toFixed(Math.min(places, 20)));
            if (isFinite(fixed)) {
                out = fixed;
            }
        }
        return out;
    }

    function todayOf(context) {
        if (own(context, 'today') && context.today !== null && context.today !== undefined) {
            return String(context.today);
        }
        var d = new Date();
        return d.getUTCFullYear() + '-' + pad2(d.getUTCMonth() + 1) + '-' + pad2(d.getUTCDate());
    }

    function pad2(n) {
        return (n < 10 ? '0' : '') + n;
    }

    function pad4(n) {
        var s = String(Math.abs(n));
        while (s.length < 4) {
            s = '0' + s;
        }
        return (n < 0 ? '-' : '') + s;
    }

    function parseDate(v) {
        if (typeof v !== 'string') {
            return null;
        }
        var m = /^(\d{4})-(\d{2})-(\d{2})(?:$|[T ]|\n$)/.exec(v);
        if (!m) {
            return null;
        }
        var y = parseInt(m[1], 10);
        var mo = parseInt(m[2], 10);
        var d = parseInt(m[3], 10);
        if (mo < 1 || mo > 12 || d < 1 || y < 1) {
            return null;
        }
        var dim = [31, (y % 4 === 0 && (y % 100 !== 0 || y % 400 === 0)) ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31][mo - 1];
        if (d > dim) {
            return null;
        }
        return [y, mo, d];
    }

    function intdiv(a, b) {
        return Math.trunc(a / b);
    }

    function dayNumber(v) {
        var p = parseDate(v);
        if (!p) {
            return null;
        }
        var y = p[0];
        var m = p[1];
        var d = p[2];
        y -= m <= 2 ? 1 : 0;
        var era = intdiv(y >= 0 ? y : y - 399, 400);
        var yoe = y - era * 400;
        var doy = intdiv(153 * (m + (m > 2 ? -3 : 9)) + 2, 5) + d - 1;
        var doe = yoe * 365 + intdiv(yoe, 4) - intdiv(yoe, 100) + doy;
        return era * 146097 + doe - 719468;
    }

    function dayToDate(z) {
        z += 719468;
        var era = intdiv(z >= 0 ? z : z - 146096, 146097);
        var doe = z - era * 146097;
        var yoe = intdiv(doe - intdiv(doe, 1460) + intdiv(doe, 36524) - intdiv(doe, 146096), 365);
        var y = yoe + era * 400;
        var doy = doe - (365 * yoe + intdiv(yoe, 4) - intdiv(yoe, 100));
        var mp = intdiv(5 * doy + 2, 153);
        var d = doy - intdiv(153 * mp + 2, 5) + 1;
        var m = mp + (mp < 10 ? 3 : -9);
        y += m <= 2 ? 1 : 0;
        return pad4(y) + '-' + pad2(m) + '-' + pad2(d);
    }

    /**
     * Evaluate a tree (or expression text).
     *
     * Context keys, all optional: values (field ID => value), today
     * ("YYYY-MM-DD"), user, query, geo, scores (field ID => points), score.
     */
    function evaluate(node, context) {
        context = context || {};
        if (typeof node === 'string') {
            node = parse(node);
        }
        switch (node.type) {
            case 'literal':
                return node.value;
            case 'ref':
                return get(get(context, 'values'), node.field);
            case 'var':
                var sc = get(context, 'score');
                return sc === null ? 0 : sc;
            case 'list':
                return node.items.map(function (n) { return evaluate(n, context); });
            case 'call':
                return call(node.name, node.args, context);
        }
        var a = node.args;
        var name = node.op;
        var ev = function (i) { return evaluate(a[i], context); };
        var l;
        var r;
        var hit;
        switch (name) {
            case 'or':
                for (var i = 0; i < a.length; i++) {
                    if (truthy(ev(i))) {
                        return true;
                    }
                }
                return false;
            case 'and':
                for (var j = 0; j < a.length; j++) {
                    if (!truthy(ev(j))) {
                        return false;
                    }
                }
                return true;
            case 'not':
                return !truthy(ev(0));
            case 'neg':
                return normalizeNumber(-num(ev(0)));
            case 'empty':
                return isEmpty(ev(0));
            case 'not_empty':
                return !isEmpty(ev(0));
            case 'is':
                return equals(ev(0), ev(1));
            case 'is_not':
                return !equals(ev(0), ev(1));
            case 'lt':
            case 'le':
            case 'gt':
            case 'ge':
                var c = compare(ev(0), ev(1));
                if (c === null) {
                    return false;
                }
                return (name === 'lt' && c < 0) || (name === 'le' && c <= 0) || (name === 'gt' && c > 0) || (name === 'ge' && c >= 0);
            case 'one_of':
            case 'not_one_of':
                var left = ev(0);
                var list = ev(1);
                list = isArr(list) ? values(list) : [list];
                hit = (isArr(left) ? values(left) : [left]).some(function (x) {
                    return list.some(function (y) { return scalarEquals(x, y); });
                });
                return name === 'one_of' ? hit : !hit;
            case 'contains':
            case 'not_contains':
                hit = contains(ev(0), ev(1));
                return name === 'contains' ? hit : !hit;
            case 'starts_with':
            case 'ends_with':
                l = lower(str(ev(0)));
                r = lower(str(ev(1)));
                if (r === '') {
                    return true;
                }
                return name === 'starts_with' ? l.indexOf(r) === 0 : (l.length >= r.length && l.slice(l.length - r.length) === r);
            case 'before':
            case 'after':
                l = dayNumber(ev(0));
                r = dayNumber(ev(1));
                if (l === null || r === null) {
                    return false;
                }
                return name === 'before' ? l < r : l > r;
            case 'within':
                l = dayNumber(ev(0));
                var n = num(ev(1));
                r = dayNumber(a.length > 2 ? ev(2) : todayOf(context));
                if (l === null || r === null) {
                    return false;
                }
                return Math.abs(l - r) <= n;
            case 'add':
            case 'sub':
                l = ev(0);
                r = ev(1);
                var ld = dayNumber(l);
                var rd = dayNumber(r);
                if (ld !== null && rd === null) {
                    var days = phpRound(num(r), 0);
                    return dayToDate(name === 'add' ? ld + days : ld - days);
                }
                if (name === 'sub' && ld !== null && rd !== null) {
                    return ld - rd;
                }
                return normalizeNumber(name === 'add' ? num(l) + num(r) : num(l) - num(r));
            case 'mul':
                return normalizeNumber(num(ev(0)) * num(ev(1)));
            case 'div':
            case 'mod':
                var dv = num(ev(1));
                if (dv === 0) {
                    return null;
                }
                l = num(ev(0));
                return normalizeNumber(name === 'div' ? l / dv : l % dv);
        }
        return null;
    }

    function call(name, args, context) {
        var arg = function (i) { return evaluate(args[i], context); };
        var vals = function () {
            var out = [];
            args.forEach(function (n) {
                var v = evaluate(n, context);
                (isArr(v) ? values(v) : [v]).forEach(function (x) { out.push(x); });
            });
            return out;
        };
        var d;
        switch (name) {
            case 'today':
                return todayOf(context);
            case 'date':
                d = dayNumber(arg(0));
                return d === null ? null : dayToDate(d);
            case 'days_between':
                var x = dayNumber(arg(0));
                var y = dayNumber(arg(1));
                return (x === null || y === null) ? null : y - x;
            case 'age':
                var born = parseDate(arg(0));
                var t = parseDate(todayOf(context));
                if (!born || !t) {
                    return null;
                }
                var years = t[0] - born[0];
                if (t[1] < born[1] || (t[1] === born[1] && t[2] < born[2])) {
                    years--;
                }
                return years;
            case 'abs':
                return Math.abs(num(arg(0)));
            case 'round':
                var places = args.length > 1 ? Math.trunc(num(arg(1))) : 0;
                return phpRound(num(arg(0)), places);
            case 'floor':
                return Math.floor(num(arg(0)));
            case 'ceil':
                return Math.ceil(num(arg(0)));
            case 'min':
            case 'max':
            case 'sum':
            case 'avg':
                var nums = [];
                vals().forEach(function (v) {
                    if (!isEmpty(v)) {
                        nums.push(num(v));
                    }
                });
                var total = nums.reduce(function (s, v) { return s + v; }, 0);
                if (name === 'sum') {
                    return total;
                }
                if (!nums.length) {
                    return null;
                }
                if (name === 'avg') {
                    return total / nums.length;
                }
                return name === 'min' ? Math.min.apply(null, nums) : Math.max.apply(null, nums);
            case 'count':
                var cv = arg(0);
                if (isArr(cv)) {
                    return values(cv).filter(function (v) { return !isEmpty(v); }).length;
                }
                return isEmpty(cv) ? 0 : 1;
            case 'length':
                return Array.from(str(arg(0))).length;
            case 'if':
                return truthy(arg(0)) ? arg(1) : arg(2);
            case 'score':
                var ref = args[0];
                var id = ref.type === 'ref' ? ref.field : str(arg(0));
                var got = get(get(context, 'scores'), id);
                return got === null ? 0 : got;
            case 'user':
                return get(get(context, 'user'), str(arg(0)));
            case 'query':
                return get(get(context, 'query'), str(arg(0)));
            case 'geo':
                return get(context, 'geo');
        }
        return null;
    }

    return {
        FUNCTIONS: FUNCTIONS,
        PRECEDENCE: PRECEDENCE,
        ExpressionError: ExpressionError,
        tokenize: tokenize,
        parse: parse,
        check: check,
        canonical: canonical,
        refs: refs,
        toText: toText,
        quote: quote,
        toSentence: toSentence,
        evaluate: evaluate,
        truthy: truthy,
        isEmpty: isEmpty,
        str: str,
        phpRound: phpRound,
        phpFloat: phpFloat
    };
}));
