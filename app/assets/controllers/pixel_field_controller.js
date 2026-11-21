import { Controller } from '@hotwired/stimulus';

const CELL = 22;
const SQUARE = 12;
// A low frame rate on purpose: the squares step instead of gliding.
const FRAME_MS = 1000 / 24;
const NOISE_SIZE = 64;
/** How many grid cells one blob of noise spans. */
const CELLS_PER_NOISE = 9;
/**
 * The RSS signal of the bottom left corner: a dot that stays, and two arcs that leave it
 * together every few seconds. Distances are in cells.
 */
const SIGNAL_PERIOD = 9;
const SIGNAL_DELAY = 2;
const SIGNAL_SPEED = 9;
const SIGNAL_DOT = 4;
const SIGNAL_ARC_GAP = 7;
const SIGNAL_ARC_WIDTH = 2;
/** Thresholds go up to 1: above that, every square of the signal is lit near the corner. */
const SIGNAL_STRENGTH = 1.4;
/** Cells per second of the first wave, which reveals the field. */
const INTRO_SPEED = 34;
const POINTER_REACH = 11;
const RIPPLE_SPEED = 24;
const RIPPLE_SECONDS = 1.3;
/** The wave that an element wired to "emit" sends out from its center. */
const PULSE_SPEED = 20;
const PULSE_SECONDS = 2.4;
/** The share of a pulse that still shows inside a calm zone: it passes behind the text, dimmed. */
const PULSE_IN_CALM = 0.45;
/** Cells left empty around the text, and cells over which the field fades back in. */
const CALM_PADDING = 1.5;
const CALM_FADE = 4;
/** The intensity above its threshold from which a square takes each tone, darkest first. */
const TONE_STEPS = [0, 0.1, 0.26, 0.5, 0.78];

// Ordered dithering: a square lights up when the intensity of the field passes the threshold
// of its cell, so a soft gradient turns into a pattern of squares that are on or off.
const BAYER = [
    0, 32, 8, 40, 2, 34, 10, 42, 48, 16, 56, 24, 50, 18, 58, 26, 12, 44, 4, 36, 14, 46, 6, 38, 60, 28, 52, 20, 62, 30,
    54, 22, 3, 35, 11, 43, 1, 33, 9, 41, 51, 19, 59, 27, 49, 17, 57, 25, 15, 47, 7, 39, 13, 45, 5, 37, 63, 31, 55, 23,
    61, 29, 53, 21,
];

const clamp = (value) => Math.min(1, Math.max(0, value));
const smooth = (value) => value * value * (3 - 2 * value);

/**
 * Paints a field of squares behind its element: noise that drifts, the dot and the arcs of
 * the RSS icon that leave the bottom left corner, a glow under the pointer, a ripple
 * on each press and one pulse from the center of an element each time it calls "emit".
 * The squares stay away from the "calm" targets, so that text remains readable; only the
 * pulses cross them, dimmed.
 */
export default class extends Controller {
    static targets = ['canvas', 'calm'];

    connect() {
        this.brush = this.canvasTarget.getContext('2d');
        this.noise = Float32Array.from({ length: NOISE_SIZE * NOISE_SIZE }, Math.random);
        this.pointer = { x: 0, y: 0, targetX: 0, targetY: 0, inside: false, glow: 0 };
        this.ripples = [];
        this.pulses = [];
        this.startedAt = performance.now();
        this.lastFrameAt = 0;
        this.visible = true;
        this.frame = null;

        this.reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
        this.onMotionChange = () => this.refresh();
        this.reducedMotion.addEventListener('change', this.onMotionChange);

        this.resizeObserver = new ResizeObserver(() => this.measure());
        this.resizeObserver.observe(this.element);

        this.visibilityObserver = new IntersectionObserver(([entry]) => {
            this.visible = entry.isIntersecting;
            this.refresh();
        });
        this.visibilityObserver.observe(this.element);

        this.measure();
    }

    disconnect() {
        cancelAnimationFrame(this.frame);
        this.frame = null;
        this.resizeObserver.disconnect();
        this.visibilityObserver.disconnect();
        this.reducedMotion.removeEventListener('change', this.onMotionChange);
    }

    point(event) {
        const cell = this.cellAt(event);
        if (!this.pointer.inside) {
            this.pointer.x = cell.x;
            this.pointer.y = cell.y;
        }
        this.pointer.targetX = cell.x;
        this.pointer.targetY = cell.y;
        this.pointer.inside = true;
    }

    release() {
        this.pointer.inside = false;
    }

    ripple(event) {
        this.ripples.push({ ...this.cellAt(event), bornAt: performance.now() });
    }

    emit(event) {
        if (this.reducedMotion.matches) {
            return;
        }

        const box = event.currentTarget.getBoundingClientRect();
        this.pulses.push({
            ...this.cellAt({ clientX: box.left + box.width / 2, clientY: box.top + box.height / 2 }),
            bornAt: performance.now(),
        });
    }

    cellAt(event) {
        const box = this.element.getBoundingClientRect();

        return {
            x: (event.clientX - box.left - this.offsetX) / CELL,
            y: (event.clientY - box.top - this.offsetY) / CELL,
        };
    }

    measure() {
        const box = this.element.getBoundingClientRect();
        const ratio = window.devicePixelRatio || 1;
        this.width = box.width;
        this.height = box.height;
        this.canvasTarget.width = Math.round(box.width * ratio);
        this.canvasTarget.height = Math.round(box.height * ratio);
        this.brush.setTransform(ratio, 0, 0, ratio, 0, 0);

        // One more column and row than fit, centered: the field bleeds evenly off every edge.
        this.columns = Math.ceil(box.width / CELL) + 1;
        this.rows = Math.ceil(box.height / CELL) + 1;
        this.offsetX = (box.width - this.columns * CELL) / 2;
        this.offsetY = (box.height - this.rows * CELL) / 2;
        // The bottom left corner of the element, in cells: the grid starts a little outside of it.
        this.cornerX = -this.offsetX / CELL;
        this.cornerY = (box.height - this.offsetY) / CELL;

        this.calmZones = this.calmTargets.map((target) => {
            const zone = target.getBoundingClientRect();

            return {
                left: (zone.left - box.left - this.offsetX) / CELL - CALM_PADDING,
                right: (zone.right - box.left - this.offsetX) / CELL + CALM_PADDING,
                top: (zone.top - box.top - this.offsetY) / CELL - CALM_PADDING,
                bottom: (zone.bottom - box.top - this.offsetY) / CELL + CALM_PADDING,
            };
        });

        const style = getComputedStyle(this.element);
        this.tones = ['dim', 'mid', 'lit', 'high', 'crest'].map((tone) =>
            style.getPropertyValue(`--pixel-${tone}`).trim(),
        );

        this.refresh();
    }

    /** Paints now, then keeps animating only while the field is on screen and motion is welcome. */
    refresh() {
        if (this.reducedMotion.matches) {
            cancelAnimationFrame(this.frame);
            this.frame = null;
            // A settled field: far enough in time for the reveal to be over.
            this.paint(60);

            return;
        }

        this.paint((performance.now() - this.startedAt) / 1000);
        if (this.visible && null === this.frame) {
            this.frame = requestAnimationFrame((now) => this.tick(now));
        }
    }

    tick(now) {
        this.frame = null;
        if (!this.visible || this.reducedMotion.matches) {
            return;
        }

        if (now - this.lastFrameAt >= FRAME_MS) {
            this.lastFrameAt = now;
            this.ripples = this.ripples.filter((ripple) => now - ripple.bornAt < RIPPLE_SECONDS * 1000);
            this.pulses = this.pulses.filter((pulse) => now - pulse.bornAt < PULSE_SECONDS * 1000);
            this.pointer.x += (this.pointer.targetX - this.pointer.x) * 0.35;
            this.pointer.y += (this.pointer.targetY - this.pointer.y) * 0.35;
            this.pointer.glow += ((this.pointer.inside ? 1 : 0) - this.pointer.glow) * 0.12;
            this.paint((now - this.startedAt) / 1000, now);
        }

        this.frame = requestAnimationFrame((next) => this.tick(next));
    }

    paint(seconds, now = performance.now()) {
        const { brush, columns, rows } = this;
        const squares = this.tones.map(() => []);
        const reach = Math.hypot(columns, rows);
        // How far the first arc of the signal is from the corner; the second one follows it.
        const signal = Math.max(0, (seconds - SIGNAL_DELAY) % SIGNAL_PERIOD) * SIGNAL_SPEED;

        for (let row = 0; row < rows; row++) {
            for (let column = 0; column < columns; column++) {
                const calm = this.calmAt(column, row);

                const edge = Math.max(Math.abs((column + 0.5) / columns - 0.5), Math.abs((row + 0.5) / rows - 0.5)) * 2;
                const fromCorner = Math.hypot(column + 0.5 - this.cornerX, this.cornerY - row - 0.5);

                const drift =
                    this.noiseAt(column / CELLS_PER_NOISE + seconds * 0.07, row / CELLS_PER_NOISE - seconds * 0.04) *
                        0.65 +
                    this.noiseAt(column / 4 - seconds * 0.05, row / 4 + seconds * 0.09) * 0.35;
                let intensity = edge ** 3 * 0.5 + Math.max(0, drift - 0.42) * (0.5 + edge);

                intensity += this.signalAt(fromCorner, signal, reach * 0.8) * SIGNAL_STRENGTH;

                const front = seconds * INTRO_SPEED - fromCorner;
                intensity = intensity * clamp(front / 6) + Math.max(0, 1 - Math.abs(front) / 2.5) * 0.9;

                if (this.pointer.glow > 0.01) {
                    const distance = Math.hypot(column - this.pointer.x, row - this.pointer.y);
                    intensity += Math.max(0, 1 - distance / POINTER_REACH) ** 2 * this.pointer.glow * 0.95;
                }

                for (const ripple of this.ripples) {
                    const age = (now - ripple.bornAt) / 1000;
                    const distance = Math.hypot(column - ripple.x, row - ripple.y);
                    intensity +=
                        Math.max(0, 1 - Math.abs(distance - age * RIPPLE_SPEED) / 1.6) * (1 - age / RIPPLE_SECONDS);
                }

                // Half Bayer, half noise that never moves: a regular texture without visible tiling.
                const threshold =
                    ((BAYER[(row & 7) * 8 + (column & 7)] + 0.5) / 64) * 0.5 +
                    this.noise[((row * 31 + column * 17) & (NOISE_SIZE * NOISE_SIZE - 1))] * 0.5;
                let pulse = 0;
                for (const { x, y, bornAt } of this.pulses) {
                    const age = (now - bornAt) / 1000;
                    const distance = Math.hypot(column - x, row - y);
                    pulse += Math.max(0, 1 - Math.abs(distance - age * PULSE_SPEED) / 2) * (1 - age / PULSE_SECONDS);
                }

                const above =
                    intensity * calm + pulse * (PULSE_IN_CALM + (1 - PULSE_IN_CALM) * calm) - threshold;
                if (above <= 0) {
                    continue;
                }

                let tone = 0;
                while (tone < TONE_STEPS.length - 1 && above >= TONE_STEPS[tone + 1]) {
                    tone++;
                }
                squares[tone].push(column, row);
            }
        }

        const inset = (CELL - SQUARE) / 2;
        brush.clearRect(0, 0, this.width, this.height);
        squares.forEach((cells, tone) => {
            brush.fillStyle = this.tones[tone];
            for (let index = 0; index < cells.length; index += 2) {
                brush.fillRect(
                    Math.round(this.offsetX + cells[index] * CELL + inset),
                    Math.round(this.offsetY + cells[index + 1] * CELL + inset),
                    SQUARE,
                    SQUARE,
                );
            }
        });
    }

    /** The dot and the two arcs of the signal: solid near the corner, then thinning out as they travel. */
    signalAt(fromCorner, firstArc, range) {
        if (fromCorner < SIGNAL_DOT) {
            return 1;
        }

        const arc = (radius) =>
            radius > SIGNAL_DOT ? clamp(2 - Math.abs(fromCorner - radius) / (SIGNAL_ARC_WIDTH / 2)) : 0;

        return Math.max(arc(firstArc), arc(firstArc - SIGNAL_ARC_GAP)) * Math.max(0, 1 - fromCorner / range);
    }

    /** 0 inside a calm zone, rising to 1 a few cells away from all of them. */
    calmAt(column, row) {
        let calm = 1;
        for (const zone of this.calmZones) {
            const outside = Math.max(zone.left - column - 1, column - zone.right, zone.top - row - 1, row - zone.bottom);
            calm = Math.min(calm, clamp(outside / CALM_FADE));
        }

        return calm;
    }

    /** Value noise between 0 and 1, which repeats every NOISE_SIZE units. */
    noiseAt(x, y) {
        const left = Math.floor(x);
        const top = Math.floor(y);
        const wrap = (value) => ((value % NOISE_SIZE) + NOISE_SIZE) % NOISE_SIZE;
        const at = (column, line) => this.noise[wrap(line) * NOISE_SIZE + wrap(column)];
        const horizontal = smooth(x - left);
        const vertical = smooth(y - top);
        const upper = at(left, top) + (at(left + 1, top) - at(left, top)) * horizontal;
        const lower = at(left, top + 1) + (at(left + 1, top + 1) - at(left, top + 1)) * horizontal;

        return upper + (lower - upper) * vertical;
    }
}
