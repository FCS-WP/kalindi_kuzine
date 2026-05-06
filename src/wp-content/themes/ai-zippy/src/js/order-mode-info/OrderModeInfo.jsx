import { useState, useEffect } from "react";
import { getGroupedCart, clearSession } from "./api.js";
import "./style.scss";

export default function OrderModeInfo() {
	const [groups, setGroups] = useState([]);
	const [loading, setLoading] = useState(true);
	const [error, setError] = useState(null);
	const [showConfirm, setShowConfirm] = useState(false);
	const [clearing, setClearing] = useState(false);

	useEffect(() => {
		console.log("🚀 OrderModeInfo component mounted");
		loadCartData();
	}, []);

	const loadCartData = async () => {
		try {
			console.log("📡 Fetching grouped cart data...");
			const result = await getGroupedCart();
			console.log("✅ Grouped cart data received:", result);
			setGroups(result);
		} catch (err) {
			console.error("❌ Error loading cart data:", err);
			setError(err.message);
		} finally {
			setLoading(false);
		}
	};

	const [targetMenuId, setTargetMenuId] = useState(null);

	const handleClearSession = async () => {
		setClearing(true);
		try {
			await clearSession(targetMenuId);
			// Refresh local data instead of full page reload if possible
			// but for now, reload to ensure everything is in sync
			window.location.reload();
		} catch (err) {
			console.error("Error clearing session:", err);
			setClearing(false);
			setShowConfirm(false);
		}
	};

	const triggerConfirm = (menuId) => {
		setTargetMenuId(menuId);
		setShowConfirm(true);
	};

	if (loading) {
		return <div className="omi-loading" style={{padding: '20px', textAlign: 'center', background: '#f5f5f5'}}>⏳ Loading cart groups...</div>;
	}

	if (error) {
		return <div className="omi-error" style={{color: 'red', padding: '20px'}}>❌ Error: {error}</div>;
	}

	if (groups.length === 0) {
		console.log("ℹ️ Cart is empty, rendering nothing");
		return <div className="omi-empty" style={{padding: '20px', color: '#999'}}>Your cart is currently empty or has no groupings.</div>;
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
				if (parsed.from && parsed.to) return `${parsed.from} - ${parsed.to}`;
			} catch { return timeData; }
		}
		if (typeof timeData === "object" && timeData.from && timeData.to) {
			return `${timeData.from} - ${timeData.to}`;
		}
		return "";
	};

	return (
		<div className="omi-grouped-container">
			{groups.map((group, index) => (
				<div key={group.menu_id || index} className="omi-group-card">
					<div className="omi-group-card__header">
						<div className="omi-group-card__info">
							<div className="omi-group-card__mode">
								<span className="omi-label">Mode:</span>
								<span className="omi-value">
									{group.session.order_mode 
										? (group.session.order_mode === "takeaway" ? "Takeaway" : "Delivery")
										: "Not set"}
								</span>
							</div>
							<div className="omi-group-card__outlet">
								<span className="omi-label">Outlet:</span>
								<span className="omi-value">{group.session.outlet_name || "Not selected"}</span>
							</div>
							<div className="omi-group-card__time">
								<span className="omi-label">Time:</span>
								<span className="omi-value">
									{group.session.date 
										? `${formatDate(group.session.date)} ${group.session.time ? `(${formatTime(group.session.time)})` : ""}`
										: "Date/Time not set"}
								</span>
							</div>
						</div>
						
						<button
							className="omi-group-card__reset"
							onClick={() => triggerConfirm(group.menu_id)}
							title="Clear this group"
						>
							Reset
						</button>
					</div>

					<div className="omi-group-card__items">
						{group.items.map((item) => (
							<div key={item.key} className="omi-item">
								<div className="omi-item__image">
									<img src={item.image} alt={item.name} />
								</div>
								<div className="omi-item__content">
									<div className="omi-item__name">{item.name}</div>
									<div className="omi-item__meta">
										<span>Qty: {item.quantity}</span>
										<span>${(item.price).toFixed(2)}</span>
									</div>
								</div>
							</div>
						))}
					</div>
				</div>
			))}

			{showConfirm && (
				<div className="omi-confirm-overlay">
					<div className="omi-confirm">
						<h3 className="omi-confirm__title">Clear this group?</h3>
						<p className="omi-confirm__message">
							This will remove all items in this group and reset its delivery info.
						</p>
						<div className="omi-confirm__actions">
							<button className="omi-confirm__btn--cancel" onClick={() => setShowConfirm(false)}>Cancel</button>
							<button className="omi-confirm__btn--confirm" onClick={handleClearSession} disabled={clearing}>
								{clearing ? "Resetting..." : "Yes, Reset"}
							</button>
						</div>
					</div>
				</div>
			)}
		</div>
	);
}