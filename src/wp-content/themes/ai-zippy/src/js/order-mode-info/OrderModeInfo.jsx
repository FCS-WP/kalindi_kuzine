import { useState, useEffect } from "react";
import { getSessionInfo, clearSession } from "./api.js";
import "./style.scss";

export default function OrderModeInfo() {
	const [data, setData] = useState(null);
	const [loading, setLoading] = useState(true);
	const [showConfirm, setShowConfirm] = useState(false);
	const [clearing, setClearing] = useState(false);

	useEffect(() => {
		loadSessionData();
	}, []);

	const loadSessionData = async () => {
		try {
			const result = await getSessionInfo();
			setData(result);
		} catch (err) {
			console.error("Error loading session info:", err);
		} finally {
			setLoading(false);
		}
	};

	const handleClearSession = async () => {
		setClearing(true);
		try {
			await clearSession();
			// Redirect to shop after clearing
			const redirectUrl = window.orderModeInfo?.shopUrl || "/shop/";
			window.location.href = redirectUrl;
		} catch (err) {
			console.error("Error clearing session:", err);
			setClearing(false);
			setShowConfirm(false);
		}
	};

	if (loading || !data || !data.order_mode) {
		return null;
	}

	const formatDate = (dateStr) => {
		if (!dateStr) return "";
		const date = new Date(dateStr);
		return date.toLocaleDateString("en-GB", {
			day: "numeric",
			month: "short",
			year: "numeric",
		});
	};

	const formatTime = (timeData) => {
		if (!timeData) return "";

		if (typeof timeData === "string") {
			try {
				const parsed = JSON.parse(timeData);
				if (parsed.from && parsed.to) {
					return `From ${parsed.from} To ${parsed.to}`;
				}
			} catch {
				return timeData;
			}
		}

		if (typeof timeData === "object" && timeData.from && timeData.to) {
			return `From ${timeData.from} To ${timeData.to}`;
		}

		return "";
	};

	return (
		<>
			<div className="omi-card">
				<button
					className="omi-card__reset"
					onClick={() => setShowConfirm(true)}
					aria-label="Reset order"
					type="button"
				>
					<svg
						viewBox="0 0 24 24"
						fill="none"
						stroke="currentColor"
						strokeWidth="2"
						strokeLinecap="round"
						strokeLinejoin="round"
						width="22"
						height="22"
					>
						<circle cx="12" cy="12" r="10" />
						<line x1="15" y1="9" x2="9" y2="15" />
						<line x1="9" y1="9" x2="15" y2="15" />
					</svg>
				</button>

				<div className="omi-card__section">
					<div className="omi-card__label">Order Mode:</div>
					<div className="omi-card__value">
						{data.order_mode === "takeaway" ? "Takeaway" : "Delivery"}
					</div>
				</div>

				<div className="omi-card__divider" />

				<div className="omi-card__section">
					<div className="omi-card__label">Select Outlet:</div>
					<div className="omi-card__value">{data.outlet_name || "-"}</div>
				</div>

				<div className="omi-card__divider" />

				<div className="omi-card__section">
					<div className="omi-card__label">
						{data.order_mode === "takeaway" ? "Takeaway Time:" : "Delivery Time:"}
					</div>
					<div className="omi-card__value">{formatDate(data.date)}</div>
					{data.time && <div className="omi-card__subvalue">{formatTime(data.time)}</div>}
				</div>
			</div>

			{showConfirm && (
				<div className="omi-confirm-overlay">
					<div className="omi-confirm">
						<div className="omi-confirm__icon">
							<svg
								viewBox="0 0 24 24"
								fill="none"
								stroke="currentColor"
								strokeWidth="2"
								strokeLinecap="round"
								strokeLinejoin="round"
								width="48"
								height="48"
							>
								<circle cx="12" cy="12" r="10" />
								<line x1="12" y1="8" x2="12" y2="12" />
								<line x1="12" y1="16" x2="12.01" y2="16" />
							</svg>
						</div>
						<h3 className="omi-confirm__title">Clear Order?</h3>
						<p className="omi-confirm__message">
							This will remove all items from your cart and reset your order. This action cannot be undone.
						</p>
						<div className="omi-confirm__actions">
							<button
								className="omi-confirm__btn omi-confirm__btn--cancel"
								onClick={() => setShowConfirm(false)}
								type="button"
								disabled={clearing}
							>
								Cancel
							</button>
							<button
								className="omi-confirm__btn omi-confirm__btn--confirm"
								onClick={handleClearSession}
								type="button"
								disabled={clearing}
							>
								{clearing ? "Clearing..." : "Yes, Clear Order"}
							</button>
						</div>
					</div>
				</div>
			)}
		</>
	);
}