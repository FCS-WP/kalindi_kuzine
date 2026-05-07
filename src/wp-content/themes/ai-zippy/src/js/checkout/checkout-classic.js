/**
 * Classic Checkout Address Autocomplete
 * Enhances billing_address_1 with OneMap search for distance calculation.
 */
document.addEventListener("DOMContentLoaded", function() {
    const addressInput = document.getElementById("billing_address_1");
    if (!addressInput) return;

    console.log("AZ DEBUG: Classic checkout autocomplete initialized");

    // Create suggestion list container
    const wrapper = document.createElement("div");
    wrapper.className = "az-autocomplete-wrapper";
    addressInput.parentNode.insertBefore(wrapper, addressInput);
    wrapper.appendChild(addressInput);

    const list = document.createElement("ul");
    list.className = "az-autocomplete-list";
    list.style.display = "none";
    wrapper.appendChild(list);

    let debounceTimer;

    addressInput.addEventListener("input", function() {
        const val = this.value.trim();
        clearTimeout(debounceTimer);

        if (val.length < 3) {
            list.style.display = "none";
            return;
        }

        debounceTimer = setTimeout(() => {
            console.log("AZ DEBUG: Searching for:", val);
            fetch(`/wp-json/ai-zippy/v1/location-proxy?keyword=${encodeURIComponent(val)}`)
                .then(res => res.json())
                .then(res => {
                    if (res.status === "success" && res.data?.results) {
                        console.log("AZ DEBUG: Results found:", res.data.results.length);
                        renderSuggestions(res.data.results);
                    } else {
                        list.style.display = "none";
                    }
                })
                .catch(err => {
                    console.error("AZ DEBUG: Search error:", err);
                    list.style.display = "none";
                });
        }, 400);
    });

    function renderSuggestions(results) {
        list.innerHTML = "";
        if (results.length === 0) {
            list.style.display = "none";
            return;
        }

        results.forEach(loc => {
            const li = document.createElement("li");
            li.textContent = loc.ADDRESS;
            li.addEventListener("click", () => {
                // Clean address if it already contains postal code at the end
                let displayAddress = loc.ADDRESS;
                if (loc.POSTAL && displayAddress.endsWith(loc.POSTAL)) {
                    displayAddress = displayAddress.substring(0, displayAddress.length - loc.POSTAL.length).trim();
                    displayAddress = displayAddress.replace(/,$/, "").trim();
                }

                console.log("AZ DEBUG: Address selected (Cleaned):", displayAddress, loc.POSTAL);

                // Update fields
                addressInput.value = displayAddress;
                const billingPostcode = document.getElementById("billing_postcode");
                if (billingPostcode) billingPostcode.value = loc.POSTAL;
                const billingCity = document.getElementById("billing_city");
                if (billingCity) billingCity.value = "Singapore";

                const shippingAddress = document.getElementById("shipping_address_1");
                if (shippingAddress) shippingAddress.value = displayAddress;
                const shippingPostcode = document.getElementById("shipping_postcode");
                if (shippingPostcode) shippingPostcode.value = loc.POSTAL;

                list.style.display = "none";
                
                const $ = window.jQuery;
                if ($) {
                    console.log("AZ DEBUG: Triggering update sequence...");
                    $(addressInput).trigger('change');
                    $(document.body).trigger('update_checkout');
                }
            });
            list.appendChild(li);
        });

        list.style.display = "block";
    }

    // Hide list when clicking outside
    document.addEventListener("click", function(e) {
        if (!wrapper.contains(e.target)) {
            list.style.display = "none";
        }
    });
});
