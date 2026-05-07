import { useState, useEffect, useCallback } from "react";
import { searchLocation } from "../api.js";

export default function AddressForm({ address, onChange, errors = {}, readOnly = false, hasPartyOrder = false }) {
	const [suggestions, setSuggestions] = useState([]);
	const [loadingSuggestions, setLoadingSuggestions] = useState(false);
	const [showSuggestions, setShowSuggestions] = useState(false);

	function update(key, value) {
		if (readOnly) return;
		onChange({ ...address, [key]: value });
	}

	const handleSearch = useCallback(async (val) => {
		if (!val || val.length < 3) {
			setSuggestions([]);
			setShowSuggestions(false);
			return;
		}

		setLoadingSuggestions(true);
		try {
			const res = await searchLocation(val);
			if (res.status === "success" && res.data?.results) {
				setSuggestions(res.data.results);
				setShowSuggestions(true);
			}
		} catch (err) {
			console.error("Search failed:", err);
		} finally {
			setLoadingSuggestions(false);
		}
	}, []);

	// Debounced search
	useEffect(() => {
		if (!hasPartyOrder) return;
		const timer = setTimeout(() => {
			if (address.address_1 && !address.address_1_selected) {
				handleSearch(address.address_1);
			}
		}, 500);
		return () => clearTimeout(timer);
	}, [address.address_1, hasPartyOrder, handleSearch]);

	const selectAddress = (loc) => {
		onChange({
			...address,
			address_1: loc.ADDRESS,
			address_1_selected: true,
			postcode: loc.POSTAL,
			city: "Singapore",
			country: "SG"
		});
		setSuggestions([]);
		setShowSuggestions(false);
	};

	if (readOnly) {
		return (
			<div className="zk__address-readonly">
				<div className="zk__field">
					<label className="zk__label">Delivery Address</label>
					<div className="zk__address-static">
						{address.address_1}
						{address.postcode && <><br />Singapore {address.postcode}</>}
					</div>
					<p className="zk__address-help">Address is locked based on your delivery selection.</p>
				</div>
			</div>
		);
	}

	return (
		<div className="zk__section-fields">
			<div className="zk__field">
				<label className="zk__label">Company (optional)</label>
				<input
					className="zk__input"
					value={address.company || ""}
					onChange={(e) => update("company", e.target.value)}
					autoComplete="organization"
				/>
			</div>

			<div className="zk__field zk__field--autocomplete">
				<label className="zk__label zk__label--required">Address</label>
				<div className="zk__input-wrapper">
					<input
						className={`zk__input${errors?.address_1 ? " is-error" : ""}`}
						value={address.address_1 || ""}
						onChange={(e) => {
							onChange({ ...address, address_1: e.target.value, address_1_selected: false });
						}}
						placeholder="Search your address..."
						autoComplete="address-line1"
					/>
					{loadingSuggestions && <span className="zk__inline-loader" />}
				</div>
				{showSuggestions && suggestions.length > 0 && (
					<ul className="zk__suggestions">
						{suggestions.map((loc, i) => (
							<li key={i} onClick={() => selectAddress(loc)}>
								<span className="zk__suggestion-main">{loc.ADDRESS}</span>
							</li>
						))}
					</ul>
				)}
				{errors?.address_1 && <span className="zk__field-error">{errors.address_1}</span>}
			</div>

			<div className="zk__field">
				<input
					className="zk__input"
					value={address.address_2 || ""}
					onChange={(e) => update("address_2", e.target.value)}
					placeholder="Apartment, suite, unit, etc. (optional)"
					autoComplete="address-line2"
				/>
			</div>

			<div className="zk__row">
				<div className="zk__field zk__field--half">
					<label className="zk__label zk__label--required">City</label>
					<input
						className={`zk__input${errors?.city ? " is-error" : ""}`}
						value={address.city || ""}
						onChange={(e) => update("city", e.target.value)}
						autoComplete="address-level2"
					/>
					{errors?.city && <span className="zk__field-error">{errors.city}</span>}
				</div>
				<div className="zk__field zk__field--half">
					<label className="zk__label">Postal code</label>
					<input
						className="zk__input"
						value={address.postcode || ""}
						onChange={(e) => update("postcode", e.target.value)}
						autoComplete="postal-code"
					/>
				</div>
			</div>

			<div className="zk__field">
				<label className="zk__label">State / Province (optional)</label>
				<input
					className="zk__input"
					value={address.state || ""}
					onChange={(e) => update("state", e.target.value)}
					autoComplete="address-level1"
				/>
			</div>
		</div>
	);
}
