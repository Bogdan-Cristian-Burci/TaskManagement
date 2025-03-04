import './bootstrap';
import Lenis from "lenis";
import 'lenis/dist/lenis.css';
import {gsap} from "gsap";
import {ScrollTrigger} from "gsap/ScrollTrigger";
import TextPlugin from "gsap/TextPlugin";
import {ScrollToPlugin} from "gsap/ScrollToPlugin";
import {Observer} from "gsap/Observer";

window.gsap = gsap;
gsap.registerPlugin(ScrollTrigger, TextPlugin, ScrollToPlugin, Observer);
const lenis = new Lenis();
lenis.on('scroll', ScrollTrigger.update);
gsap.ticker.add(time => {
    lenis.raf(time * 1000);
});
gsap.ticker.lagSmoothing(0);


const initIndexPage = () => {

    gsap.to('.mouse-element', {
        duration: 1,
        y: -10,
        ease: 'bounce',
        repeat: -1,
        yoyo: true
    });

    let sideNav = gsap.utils.toArray('.side-menu li');

    const fillBackground = (elem, empty) => {
        if (empty) {
            elem.classList.add('bg-transparent');
            elem.classList.remove('bg-colab-accent');
        } else {
            elem.classList.remove('bg-transparent');
            elem.classList.add('bg-colab-accent');
        }
    };

    const servicesSection = document.querySelector('.services-section');
    const servicesArr = document.querySelectorAll('.service-card');
    const serviceTextElement = document.querySelector('#our-services');

    if (servicesSection && servicesArr.length > 0) {
        let horizontalScroll = gsap.to(servicesArr, {
            xPercent: -100 * (servicesArr.length - 1),
            ease: 'none',
            scrollTrigger: {
                trigger: servicesSection,
                pin: true,
                scrub: 1,
                snap: 1 / (servicesArr.length - 1),
                end: () => '+=' + servicesSection.offsetWidth,
            }
        });

        ScrollTrigger.create({
            containerAnimation: horizontalScroll,
            trigger: ".resume-builder",
            start: 'left center',
            onEnter: self => gsap.to(serviceTextElement, { duration: .5, text: "Build Your Resume for Free", ease: "power1.inOut" }),
            onLeaveBack: self => gsap.to(serviceTextElement, { duration: .5, text: "Our services", ease: "power1.inOut" })
        });
        ScrollTrigger.create({
            containerAnimation: horizontalScroll,
            trigger: ".task-organiser",
            start: 'left center',
            onEnter: self => gsap.to(serviceTextElement, { duration: .5, text: "Task Organiser", ease: "power1.inOut" }),
            onLeaveBack: self => gsap.to(serviceTextElement, { duration: .5, text: "Build Your Resume for Free", ease: "power1.inOut" })
        });
        ScrollTrigger.create({
            containerAnimation: horizontalScroll,
            trigger: ".crm-service",
            start: 'left center',
            onEnter: self => gsap.to(serviceTextElement, { duration: .5, text: "Keep your business organised", ease: "power1.inOut" }),
            onLeaveBack: self => gsap.to(serviceTextElement, { duration: .5, text: "Task Organiser", ease: "power1.inOut" })
        });
    }
};

const initDialogSvg = () => {
    const speechBubbleTL = gsap.timeline({ paused: true, repeat: 0, yoyo: false });

    speechBubbleTL.to('#Bubble_7', {
        duration: 0.3,
        y: -5,
        ease: 'bounce'
    }).to('#Bubble_8', {
        duration: 0.3,
        y: -5,
        ease: 'bounce'
    }).to('#Bubble_9', {
        duration: 0.3,
        y: -5,
        ease: 'bounce'
    }).to('#Dialog_Box_3', {
        duration: 1,
        scale: 1.8,
        transformOrigin: 'center center',
        ease: "power1.inOut",
    }).to(['#Bubble_7', '#Bubble_8', '#Bubble_9'], {
        duration: 0,
        autoAlpha: 0
    }).to("#Dialog_Box_3_text_1", {
        duration: 1,
        text: "Practice",
        ease: "power1.inOut"
    }).to("#Dialog_Box_3_text_2", {
        duration: 1,
        text: "your english",
        ease: "power1.inOut"
    }).to("#Dialog_Box_3_text_3", {
        duration: 1,
        text: "skills!!",
        ease: "power1.inOut"
    }).to('#Bubble_1', {
        duration: 0.3,
        y: -5,
        ease: 'bounce'
    }).to('#Bubble_2', {
        duration: 0.3,
        y: -5,
        ease: 'bounce'
    }).to('#Bubble_3', {
        duration: 0.3,
        y: -5,
        ease: 'bounce'
    }).to('#Dialog_Box_1', {
        duration: 1,
        scale: 1.8,
        transformOrigin: 'center center',
        ease: "power1.inOut",
    }).to(['#Bubble_1', '#Bubble_2', '#Bubble_3'], {
        duration: 0,
        autoAlpha: 0
    }).to("#Dialog_Box_1_Text", {
        duration: 1,
        text: "Hello!!!",
        ease: "power1.inOut"
    }).to('#Bubble_4', {
        duration: 0.3,
        y: -5,
        ease: 'bounce'
    }).to('#Bubble_5', {
        duration: 0.3,
        y: -5,
        ease: 'bounce'
    }).to('#Bubble_6', {
        duration: 0.3,
        y: -5,
        ease: 'bounce'
    }).to('#Dialog_Box_2', {
        duration: 1,
        scale: 1.8,
        transformOrigin: 'center center',
        ease: "power1.inOut",
    }).to(['#Bubble_4', '#Bubble_5', '#Bubble_6'], {
        duration: 0,
        autoAlpha: 0
    }).to("#Dialog_Box_2_Text", {
        duration: 1,
        text: "Kitty?!?",
        ease: "power1.inOut"
    });

    const dialogSvg = document.querySelector('#svg-dialogue');

    if (dialogSvg) {
        dialogSvg.addEventListener('mouseenter', () => {
            speechBubbleTL.play();
        });
        dialogSvg.addEventListener('mouseleave', () => {
            speechBubbleTL.seek(0).pause();
        });
    }
};

const resetPasswordPage = ()=>{
    // Page animation timeline
    const tl = gsap.timeline({ defaults: { ease: "power3.out" } });

    tl.to("#pageTitle", { opacity: 1, y: -10, duration: 0.6, delay: 0.2 })
        .to("#resetForm", { opacity: 1, y: -10, duration: 0.6 }, "-=0.3")
        .to("#loginLink", { opacity: 1, y: -10, duration: 0.6 }, "-=0.3");

    // Button animation
    const button = document.getElementById("submitButton");
    button.addEventListener("mouseenter", () => {
        gsap.to(button, { scale: 1.03, duration: 0.3 });
    });

    button.addEventListener("mouseleave", () => {
        gsap.to(button, { scale: 1, duration: 0.3 });
    });

    // Form submission animation
    document.querySelector("form").addEventListener("submit", function(e) {
        const form = this;
        e.preventDefault();

        gsap.to("#resetForm", {
            y: -10,
            opacity: 0.7,
            duration: 0.3,
            onComplete: () => {
                form.submit();
            }
        });
    });
}

// Initialize page-specific logic
document.addEventListener('DOMContentLoaded', () => {
    if (document.querySelector('.services-section')) {
        initIndexPage();
    }
    if (document.querySelector('#svg-dialogue')) {
        initDialogSvg();
    }

    if (document.querySelector('#resetForm')) {
        resetPasswordPage();
    }
});
