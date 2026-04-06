import { useRef, useEffect } from "react";

/**
 * Checkout section card with numbered title and chevron.
 *
 * States:
 * - "editing"   → form is open, card elevated
 * - "completed" → shows summary with edit button
 * - "upcoming"  → collapsed, dimmed
 */
export default function CheckoutSection({
	number,
	title,
	state,
	summary,
	onEdit,
	children,
}) {
	const ref = useRef(null);

	useEffect(() => {
		if (state === "editing" && ref.current) {
			ref.current.scrollIntoView({ behavior: "smooth", block: "start" });
		}
	}, [state]);

	const isEditing = state === "editing";
	const isCompleted = state === "completed";

	return (
		<div
			ref={ref}
			className={`zk__box${isEditing ? " is-editing" : ""}${isCompleted ? " is-completed" : ""}${state === "upcoming" ? " is-upcoming" : ""}`}
		>
			<div className="zk__box-header" onClick={isCompleted ? onEdit : undefined}>
				<span className="zk__box-num">{number}.</span>
				<h3 className="zk__box-title">{title}</h3>

				{isCompleted && (
					<button className="zk__box-edit" onClick={onEdit} type="button">
						Edit
					</button>
				)}

				<svg className="zk__box-chevron" viewBox="0 0 20 20" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round">
					<polyline points="5 8 10 13 15 8" />
				</svg>
			</div>

			{isCompleted && summary && (
				<div className="zk__box-summary">{summary}</div>
			)}

			{isEditing && (
				<div className="zk__box-body">{children}</div>
			)}
		</div>
	);
}
