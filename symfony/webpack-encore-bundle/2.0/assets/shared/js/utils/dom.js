/**
 * @param {HTMLElement} element
 * @param {number} speed
 */
export const removeFadeOut = (element, speed = 300) => {
    const seconds = speed / 1000;
    element.style.transition = `opacity ${seconds}s ease`;
    element.style.opacity = '0';
    setTimeout(() => element.parentNode.removeChild(element), speed);
}

/**
 * @param  {HTMLElement} element
 * @param {string} prefix
 * @param {string} replace
 * @returns {HTMLElement}
 */
export const removeClassByPrefix = (element, prefix, replace = '') => {
    const regex = new RegExp('\\b' + prefix + '[^ ]*[ ]?\\b', 'g');
    element.className = element.className.replace(regex, replace);
    return element;
}

/**
 * @param {HTMLElement} element
 * @param {string} loadingText
 */
export const createButtonLoader = (element, loadingText = 'chargement...') => {
    element.classList.add('disabled')
    element.setAttribute('disabled', 'disabled')
    element.setAttribute('aria-disabled', 'true')
    element.setAttribute('tabindex', '-1')

    let icon = element.querySelector('.icon')
    if (icon) {
        removeClassByPrefix(icon, 'ni-', 'ni-loader')
            .setAttribute('aria-label', 'loader')
    } else {
        element.innerHTML = `
            <em class="icon ni ni-loader" role="img" aria-label="loader"></em>
            <span>${loadingText}</span>
        `
    }
}

/**
 * @param {HTMLButtonElement|HTMLAnchorElement} element
 * @param {string} content
 */
export const removeButtonLoader = (element, content) => {
    element.classList.remove('disabled')
    element.removeAttribute('disabled')
    element.setAttribute('aria-disabled', 'false');
    element.setAttribute('tabindex', '0')
    element.innerHTML = content
}

/**
 * Trouve la position de l'élément par rapport au haut de la page de manière recursive
 *
 * @param {HTMLElement} element
 * @param {HTMLElement|null} parent
 */
export function offsetTop(element, parent = null) {
    let top = element.offsetTop;
    while ((element = element.offsetParent)) {
        if (parent === element) {
            return top;
        }
        top += element.offsetTop;
    }
    return top;
}

/**
 * Crée un élément HTML
 *
 * Cette fonction ne couvre que les besoins de l'application, jsx-dom pourrait remplacer cette fonction
 *
 * @param {string} tagName
 * @param {object} attributes
 * @param {...HTMLElement|string} children
 * @return HTMLElement
 */
export function createElement(tagName, attributes = {}, ...children) {
    if (typeof tagName === "function") {
        return tagName(attributes);
    }

    const svgTags = ["svg", "use", "path", "circle", "g"];
    // On construit l'élément
    const e = !svgTags.includes(tagName)
        ? document.createElement(tagName)
        : document.createElementNS("http://www.w3.org/2000/svg", tagName);

    // On lui associe les bons attributs
    for (const k of Object.keys(attributes || {})) {
        if (typeof attributes[k] === "function" && k.startsWith("on")) {
            e.addEventListener(k.substr(2).toLowerCase(), attributes[k]);
        } else if (k === "xlink:href") {
            e.setAttributeNS("http://www.w3.org/1999/xlink", "href", attributes[k]);
        } else {
            e.setAttribute(k, attributes[k]);
        }
    }

    // On aplatit les enfants
    children = children.reduce((acc, child) => {
        return Array.isArray(child) ? [...acc, ...child] : [...acc, child];
    }, []);

    // On ajoute les enfants à l'élément
    for (const child of children) {
        if (typeof child === "string" || typeof child === "number") {
            e.appendChild(document.createTextNode(child));
        } else if (child instanceof HTMLElement || child instanceof SVGElement) {
            e.appendChild(child);
        } else {
            console.error("Impossible d'ajouter l'élément", child, typeof child);
        }
    }
    return e;
}

/**
 * Transform une chaine en élément DOM
 * @param {string} str
 * @return {DocumentFragment}
 */
export function strToDom(str) {
    return document.createRange().createContextualFragment(str).firstChild;
}

/**
 *
 * @param {HTMLElement|Document|Node} element
 * @param {string} selector
 * @return {null|HTMLElement}
 */
export function closest(element, selector) {
    for (; element && element !== document; element = element.parentNode) {
        if (element.matches(selector)) {
            return element;
        }
    }
    return null;
}

/**
 * Génère une classe à partir de différentes variables
 *
 * @param  {...string|null} classnames
 */
export function classNames(...classnames) {
    return classnames.filter((classname) => classname !== null && classname !== false).join(" ");
}

/**
 * Convertit les données d'un formulaire en objet JavaScript
 *
 * @param {HTMLFormElement} form
 * @return {*}
 */
export function formDataToObj(form) {
    return Object.fromEntries(new FormData(form));
}
